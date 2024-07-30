<?php

namespace Enjin\Platform\Beam\Services;

use Closure;
use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Events\BeamClaimPending;
use Enjin\Platform\Beam\Events\BeamCreated;
use Enjin\Platform\Beam\Events\BeamDeleted;
use Enjin\Platform\Beam\Events\BeamUpdated;
use Enjin\Platform\Beam\Events\TokensAdded;
use Enjin\Platform\Beam\Events\TokensRemoved;
use Enjin\Platform\Beam\Exceptions\BeamException;
use Enjin\Platform\Beam\Jobs\ClaimBeam;
use Enjin\Platform\Beam\Jobs\CreateClaim;
use Enjin\Platform\Beam\Jobs\DispatchCreateBeamClaimsJobs;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamPack;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Beam\Support\ClaimProbabilities;
use Enjin\Platform\Support\BitMask;
use Enjin\Platform\Support\Blake2;
use Enjin\Platform\Support\SS58Address;
use Facades\Enjin\Platform\Beam\Services\BeamService as BeamServiceFacade;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class BeamService
{
    use IntegerRange;

    /**
     * The signing request prefix.
     */
    public const SIGNING_REQUEST_PREFIX = 'epsr:';

    /**
     * The probability instance.
     */
    protected $probability;

    /**
     * Create new beam service instance.
     */
    public function __construct(
        protected BatchService $batch
    ) {
        $this->probability = new ClaimProbabilities();
    }

    /**
     * Get flags bitmask value.
     */
    public static function getFlagsValue(?array $flags, int $initial = 0): ?int
    {
        if (is_null($flags)) {
            return 0;
        }

        return collect($flags)->reduce(function ($carry, $flagInput) {
            $flag = BeamFlag::getEnumCase($flagInput['flag']);
            $method = Arr::get($flagInput, 'enabled', true) ? 'setBit' : 'unsetBit';

            return BitMask::$method($flag->value, $carry);
        }, $initial);
    }

    /**
     * Create a beam.
     */
    public function create(array $args): Model
    {
        $beam = Beam::create([
            ...Arr::except($args, ['tokens', 'flags']),
            'flags_mask' => static::getFlagsValue(Arr::get($args, 'flags')),
            'code' => bin2hex(openssl_random_pseudo_bytes(16)),
        ]);
        if ($beam) {
            Cache::forever(
                self::key($beam->code),
                $this->createClaims(Arr::get($args, 'tokens', []), $beam)
            );
            event(new BeamCreated($beam));

            return $beam;
        }

        return throw new BeamException(__('enjin-platform-beam::error.unable_to_save'));
    }

    /**
     * Create a beam pack.
     */
    public function createPack(array $args): Model
    {
        $beam = Beam::create([
            ...Arr::except($args, ['packs', 'flags']),
            'flags_mask' => static::getFlagsValue(Arr::get($args, 'flags')),
            'code' => bin2hex(openssl_random_pseudo_bytes(16)),
            'is_pack' => true,
        ]);
        if ($beam) {
            $this->createPackClaims($beam, Arr::get($args, 'packs', []), true);
            event(new BeamCreated($beam));

            return $beam;
        }

        return throw new BeamException(__('enjin-platform-beam::error.unable_to_save'));
    }

    /**
     * Update beam by code.
     */
    public function updateByCode(string $code, array $values): Model
    {
        $beam = Beam::whereCode($code)->firstOrFail();

        if (isset($values['flags']) && count($values['flags'])) {
            $values['flags_mask'] = static::getFlagsValue($values['flags'], $beam->flags_mask ?? 0);
        }

        if ($beam->fill($values)->save()) {
            if ($tokens = Arr::get($values, 'tokens', [])) {
                Cache::increment(
                    self::key($beam->code),
                    $this->createClaims($tokens, $beam)
                );
                TokensAdded::safeBroadcast(event: ['beamCode' => $beam->code, 'code' => $code, 'tokenIds' => collect($tokens)->pluck('tokenIds')->all()]);
            }
            event(new BeamUpdated($beam));

            return $beam;
        }

        return throw new BeamException(__('enjin-platform-beam::error.unable_to_save'));
    }

    /**
     * Update beam by code.
     */
    public function addTokens(string $code, array $tokens): bool
    {
        $beam = Beam::whereCode($code)->firstOrFail();
        Cache::increment(
            self::key($beam->code),
            $this->createClaims($tokens, $beam)
        );
        TokensAdded::safeBroadcast(event: ['beamCode' => $beam->code, 'code' => $code, 'tokenIds' => collect($tokens)->pluck('tokenIds')->all()]);

        return true;
    }

    /**
     * Find beam by code.
     */
    public function findByCode(string $code): ?Model
    {
        return Beam::whereCode($code)->first();
    }

    /**
     * Scan beam by code.
     */
    public function scanByCode(string $code, ?string $wallet = null): ?Model
    {
        $beamCode = static::getSingleUseCodeData($code)?->beamCode;
        $beam = Beam::whereCode($beamCode ?? $code)->firstOrFail();
        ($beam->is_pack ? new BeamPack() : new BeamClaim())
            ->withSingleUseCode($code)
            ->firstOrFail();

        if ($wallet) {
            // Pushing this to the queue for performance
            CreateClaim::dispatch($claim = [
                'beam_id' => $beam->id,
                'wallet_public_key' => SS58Address::getPublicKey($wallet),
                'message' => self::generateSigningRequestMessage(),
            ]);

            $beam->setRelation('scans', collect(json_decode(json_encode([$claim]))));
        }

        if ($beamCode) {
            $beam['code'] = $code;
        }

        return $beam;
    }

    /**
     * Claim a beam.
     */
    public function claim(string $code, string $wallet, ?string $idempotencyKey = null): bool
    {
        $singleUseCode = null;
        $singleUse = static::getSingleUseCodeData($code);
        $beam = $this->findByCode($singleUse ? $singleUse->beamCode : $code);
        if (! $beam) {
            throw new BeamException(__('enjin-platform-beam::error.beam_not_found', ['code' => $code]));
        }

        if ($singleUse) {
            if (!($beam->is_pack ? new BeamPack() : new BeamClaim())
                ->withSingleUseCode($code)
                ->first()) {
                throw new BeamException(__('enjin-platform-beam::error.beam_not_found', ['code' => $code]));
            }
            $singleUseCode = $singleUse->claimCode;
            $code = $singleUse->beamCode;
        }

        $lock = Cache::lock(self::key($code, 'claim-lock'), 5);

        try {
            $lock->block(5);

            $key = static::key($code);
            if ((int) Cache::get($key, static::claimsCountResolver($code)) < 1) {
                throw new BeamException(__('enjin-platform-beam::error.no_more_claims'));
            }

            ClaimBeam::dispatch($claim = $this->buildClaimBeamData($wallet, $beam, $singleUseCode, $idempotencyKey));
            event(new BeamClaimPending($claim));
            Cache::decrement($key);
            Log::info("Claim beam: {$code}, Remaining: " . Cache::get($key), $claim);
        } catch (LockTimeoutException $e) {
            throw new BeamException(__('enjin-platform-beam::error.unable_to_process'));
        } finally {
            $lock?->release();
        }

        return true;
    }

    /**
     * Get claims token ID count resolver.
     */
    public static function claimsCountResolver(string $code): Closure
    {
        return function () use ($code) {
            if (self::isSingleUse($code)) {
                $singleUseClaim = BeamClaim::withSingleUseCode($code)->first();

                return (int) empty($singleUseClaim?->claimed_at);
            }

            Cache::forever(
                self::key($code),
                $count = BeamClaim::claimable()->hasCode($code)->count()
            );

            return $count;
        };
    }

    /**
     * Generate a signing request message.
     */
    public static function generateSigningRequestMessage(): string
    {
        return self::SIGNING_REQUEST_PREFIX . Blake2::hash(HexConverter::stringToHex(Str::random(20)));
    }

    /**
     * Generate cache key.
     */
    public static function key(string $name, ?string $suffix = null): string
    {
        return PlatformBeamCache::CLAIM_COUNT->key($name, $suffix);
    }

    /**
     * Expire single use codes.
     */
    public function expireSingleUseCodes(array $codes): int
    {
        $beamCodes = collect($codes)
            ->keyBy(fn ($code) => static::getSingleUseCodeData($code)->beamCode)
            ->all();

        Beam::whereIn('code', array_keys($beamCodes))
            ->get(['id', 'code', 'is_pack'])
            ->each(function ($beam) use ($beamCodes) {
                if ($claim = ($beam->is_pack ? new BeamPack() : new BeamClaim())
                    ->claimable()
                    ->where('beam_id', $beam->id)
                    ->withSingleUseCode($beamCodes[$beam->code])
                    ->first()
                ) {
                    $claim->increment('nonce');
                    Cache::decrement($this->key($beam->code));
                }
            });

        return count($codes);
    }

    /**
     * Check if beam code is single use.
     */
    public static function hasSingleUse(?string $code): bool
    {
        if (! $code) {
            return false;
        }

        return (bool) BeamServiceFacade::findByCode($code)?->hasFlag(BeamFlag::SINGLE_USE);
    }

    /**
     * Check if code is an encrypted single use.
     */
    public static function isSingleUse(?string $code): bool
    {
        if (! $code) {
            return false;
        }

        return static::hasSingleUse(static::getSingleUseCodeData($code)?->beamCode);
    }

    public static function getSingleUseCodeData(string $code): ?object
    {
        try {
            [$claimCode, $beamCode, $nonce] = explode(':', decrypt($code), 3);

            return (object) [
                'claimCode' => $claimCode,
                'beamCode' => $beamCode,
                'nonce' => $nonce,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Delete beam by code.
     */
    public function deleteByCode(string $code): bool
    {
        if ($beam = $this->findByCode($code)) {
            $beam->delete();
            event(new BeamDeleted($beam));

            return true;
        }

        return false;
    }

    /**
     * Remove beam pack tokens.
     */
    public function removeBeamPack(string $code, array $packs): bool
    {
        $packCollection = collect($packs)->keyBy('id');
        $deletedTokens = 0;
        $forDeletion = [];
        foreach ($packCollection as $pack) {
            if (empty($pack['tokenIds'])) {
                $forDeletion[] = $pack['id'];

                continue;
            }

            [$tokenIds, $tokenIdRanges] = collect($pack['tokenIds'])->partition(fn ($val) => $this->integerRange($val) === false);
            if ($tokenIds) {
                $deletedTokens += BeamClaim::where('beam_pack_id', $pack['id'])
                    ->whereNull('claimed_at')
                    ->whereIn('token_chain_id', $tokenIds)
                    ->delete();
            }

            if ($tokenIdRanges) {
                $deletedTokens += BeamClaim::where('beam_pack_id', $pack['id'])
                    ->whereNull('claimed_at')
                    ->where(function ($query) use ($tokenIdRanges) {
                        $tokenIdRanges->each(function ($tokenString) use ($query) {
                            $ranges = $this->integerRange($tokenString);
                            $query->orWhereBetween('token_chain_id', [(int) $ranges[0], (int) $ranges[1]]);
                        });
                    })
                    ->delete();
            }
        }

        $beamPacks = BeamPack::whereHas('beam', fn ($query) => $query->where('code', $code))
            ->whereIn('id', $packCollection->pluck('id'))
            ->withCount('claims')
            ->get(['id']);
        $forDeletion = array_merge($forDeletion, $beamPacks->where('claims_count', 0)->pluck('id')->all());
        if (count($forDeletion)) {
            BeamPack::whereIn('id', $forDeletion)
                ->whereDoesntHave('claims', fn ($query) => $query->whereNotNull('claimed_at'))
                ->delete();
        }

        if ($deletedTokens) {
            TokensRemoved::safeBroadcast(event: ['code' => $code, 'tokenIds' => $packCollection->pluck('tokenIds')->flatten()->all()]);
        }

        return true;
    }

    /**
     * Remove tokens from a beam.
     */
    public function removeTokens(string $code, array $tokens): bool
    {
        $integers = collect($tokens)->filter(fn ($val) => $this->integerRange($val) === false)->all();
        if ($integers) {
            Cache::decrement(
                self::key($code),
                BeamClaim::whereIn('token_chain_id', $integers)
                    ->whereHas('beam', fn ($query) => $query->where('code', $code))
                    ->whereNull('claimed_at')
                    ->delete()
            );
        }

        $ranges = collect($tokens)->filter(fn ($val) => $this->integerRange($val) !== false)->all();
        foreach ($ranges as $range) {
            [$from, $to] = $this->integerRange($range);
            Cache::decrement(
                self::key($code),
                BeamClaim::whereBetween('token_chain_id', [(int) $from, (int) $to])
                    ->whereHas('beam', fn ($query) => $query->where('code', $code))
                    ->whereNull('claimed_at')
                    ->delete()
            );
        }

        if ($tokens) {
            $this->probability->removeTokens($code, $tokens);
            TokensRemoved::safeBroadcast(event: ['code' => $code, 'tokenIds' => $tokens]);
        }

        return true;
    }

    /**
     * Update beam pack by code.
     */
    public function updatePackByCode(string $code, array $values): Model
    {
        $beam = Beam::whereCode($code)->firstOrFail();

        if (isset($values['flags']) && count($values['flags'])) {
            $values['flags_mask'] = static::getFlagsValue($values['flags'], $beam->flags_mask ?? 0);
        }

        if ($beam->fill($values)->save()) {
            if ($packs = Arr::get($values, 'packs', [])) {
                $this->createPackClaims($beam, $packs);
            }
            event(new BeamUpdated($beam));

            return $beam;
        }

        return throw new BeamException(__('enjin-platform-beam::error.unable_to_save'));
    }

    /**
     * Create beam pack claims.
     */
    public function createPackClaims(Model $beam, array $packs, bool $isNew = false): bool
    {
        if (empty($packs)) {
            return false;
        }

        $quantity = 0;
        foreach ($packs as $pack) {
            if (!($id = Arr::get($pack, 'id'))) {
                $quantity++;
            }

            $model = BeamPack::firstOrcreate(['id' => $id], [
                'beam_id' => $beam->id,
                'code' => bin2hex(openssl_random_pseudo_bytes(16)),
                'nonce' => 1,
            ]);

            $tokens = collect($pack['tokens']);
            $tokenIds = $tokens->whereNotNull('tokenIds');

            if ($tokenIds->count()) {
                DispatchCreateBeamClaimsJobs::dispatch($beam, $tokenIds->all(), $model->id)->afterCommit();
            }

            $tokenUploads = $tokens->whereNotNull('tokenIdDataUpload');
            if ($tokenUploads->count()) {
                $ids = $tokenIds->pluck('tokenIds');
                $tokenUploads->each(function ($token) use ($beam, $ids, $model) {
                    LazyCollection::make(function () use ($token, $ids) {
                        $handle = fopen($token['tokenIdDataUpload']->getPathname(), 'r');
                        while (($line = fgets($handle)) !== false) {
                            if (! $this->tokenIdExists($ids->all(), $tokenId = trim($line))) {
                                $ids->push($tokenId);
                                yield $tokenId;
                            }
                        }
                        fclose($handle);
                    })->chunk(10000)->each(function (LazyCollection $tokenIds) use ($beam, $token, $model) {
                        $token['tokenIds'] = $tokenIds->all();
                        unset($token['tokenIdDataUpload']);
                        DispatchCreateBeamClaimsJobs::dispatch($beam, [$token], $model->id)->afterCommit();
                        unset($tokenIds, $token);
                    });
                });
            }

        }

        if ($isNew) {
            return Cache::forever(self::key($beam->code), count($packs));
        }

        TokensAdded::safeBroadcast(
            event: [
                'beamCode' => $beam->code,
                'code' => $beam->code,
                'tokenIds' => collect($packs)->map(fn ($pack) => $pack['tokens'])->flatten()->pluck('tokenIds')->all(),
            ]
        );

        return Cache::increment(self::key($beam->code), $quantity);
    }

    /**
     * Create beam claims.
     */
    protected function createClaims(array $tokens, Model $beam): int
    {
        $totalClaimCount = 0;
        $tokens = collect($tokens);
        $tokenIds = $tokens->whereNotNull('tokenIds');
        if ($tokenIds->count()) {
            $totalClaimCount = $tokenIds->reduce(function ($carry, $token) {
                return collect($token['tokenIds'])->reduce(function ($val, $tokenId) use ($token) {
                    $range = $this->integerRange($tokenId);

                    return $val + (
                        $range === false
                        ? $token['claimQuantity']
                        : (($range[1] - $range[0]) + 1) * $token['claimQuantity']
                    );
                }, $carry);
            }, $totalClaimCount);
            $this->probability->createOrUpdateProbabilities($beam->code, $tokens->all());

            DispatchCreateBeamClaimsJobs::dispatch($beam, $tokenIds->all())->afterCommit();
        }

        $tokenUploads = $tokens->whereNotNull('tokenIdDataUpload');
        if ($tokenUploads->count()) {
            $ids = $tokenIds->pluck('tokenIds');
            $tokenUploads->each(function ($token) use ($beam, $ids, &$totalClaimCount) {
                LazyCollection::make(function () use ($token, $ids) {
                    $handle = fopen($token['tokenIdDataUpload']->getPathname(), 'r');
                    while (($line = fgets($handle)) !== false) {
                        if (! $this->tokenIdExists($ids->all(), $tokenId = trim($line))) {
                            $ids->push($tokenId);
                            yield $tokenId;
                        }
                    }
                    fclose($handle);
                })->chunk(10000)->each(function (LazyCollection $tokenIds) use ($beam, $token, &$totalClaimCount) {
                    $token['tokenIds'] = $tokenIds->all();
                    $totalClaimCount = $tokenIds->reduce(function ($carry, $tokenId) use ($token) {
                        $range = $this->integerRange($tokenId);

                        return $carry + (
                            $range === false
                            ? $token['claimQuantity']
                            : (($range[1] - $range[0]) + 1) * $token['claimQuantity']
                        );
                    }, $totalClaimCount);
                    unset($token['tokenIdDataUpload']);
                    $this->probability->createOrUpdateProbabilities($beam->code, [$token]);

                    DispatchCreateBeamClaimsJobs::dispatch($beam, [$token])->afterCommit();
                    unset($tokenIds, $token);
                });
            });
        }

        return $totalClaimCount;
    }

    /**
     * Build claim payload.
     */
    protected function buildClaimBeamData(
        string $wallet,
        Model $beam,
        ?string $singleUseCode = null,
        ?string $idempotencyKey = null
    ): array {
        return array_merge(
            $this->buildRequiredClaimBeamData($wallet, $beam, $singleUseCode, $idempotencyKey),
            ['extras' => $this->buildExtrasClaimBeamData($wallet, $beam)]
        );
    }

    /**
     * Build claim default data.
     */
    protected function buildRequiredClaimBeamData(
        string $wallet,
        Model $beam,
        ?string $singleUseCode = null,
        ?string $idempotencyKey = null
    ): array {
        return [
            'wallet_public_key' => SS58Address::getPublicKey($wallet),
            'claimed_at' => now(),
            'state' => ClaimStatus::PENDING->name,
            'beam' => $beam->toArray(),
            'beam_id' => $beam->id,
            'is_pack' => $beam->is_pack,
            'ip_address' => request()->getClientIp(),
            'code' => $singleUseCode,
            'idempotency_key' => $idempotencyKey ?: Str::uuid()->toString(),
        ];
    }

    /**
     * Build claim extra data.
     */
    protected function buildExtrasClaimBeamData(string $wallet, Model $beam): array
    {
        return [];
    }
}
