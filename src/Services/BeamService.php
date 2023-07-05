<?php

namespace Enjin\Platform\Beam\Services;

use Closure;
use Enjin\BlockchainTools\HexConverter;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Enums\ClaimStatus;
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
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Support\BitMask;
use Enjin\Platform\Support\Blake2;
use Enjin\Platform\Support\SS58Address;
use Facades\Enjin\Platform\Beam\Services\BeamService as BeamServiceFacade;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class BeamService
{
    use IntegerRange;

    /**
     * The cache prefix.
     */
    public const CACHE_PREFIX = 'enjin-platform:beam:';

    /**
     * The signing request prefix.
     */
    public const SIGNING_REQUEST_PREFIX = 'epsr:';

    /**
     * Create new beam service instance.
     */
    public function __construct(
        protected BatchService $batch
    ) {
    }

    /**
     * Get flags bitmask value.
     */
    public static function getFlagsValue(?array $flags, int $initial = 0): int|null
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
                TokensAdded::dispatch(['code' => $code, 'tokenIds' => collect($tokens)->pluck('tokenIds')->all()]);
            }
            event(new BeamUpdated($beam));

            return $beam;
        }

        return throw new BeamException(__('enjin-platform-beam::error.unable_to_save'));
    }

    /**
     * Find beam by code.
     */
    public function findByCode(string $code): Model|null
    {
        return Beam::whereCode($code)->first();
    }

    /**
     * Scan beam by code.
     */
    public function scanByCode(string $code, ?string $wallet = null): Model|null
    {
        $isSingleUse = static::isSingleUse($code);

        $beam = $isSingleUse
                ? BeamClaim::withSingleUseCode($code)
                    ->with('beam')
                    ->first()
                    ->beam
                : $this->findByCode($code);
        if ($wallet) {
            // Pushing this to the queue for performance
            CreateClaim::dispatch($claim = [
                'beam_id' => $beam->id,
                'wallet_public_key' => SS58Address::getPublicKey($wallet),
                'message' => self::generateSigningRequestMessage(),
            ]);

            $beam->setRelation('scans', collect(json_decode(json_encode([$claim]))));
        }

        if ($isSingleUse) {
            $beam['code'] = $code;
        }

        return $beam;
    }

    /**
     * Claim a beam.
     */
    public function claim(string $code, string $wallet): bool
    {
        $singleUseCode = null;
        $singleUse = static::isSingleUse($code);

        if ($singleUse) {
            $singleUseCode = $code;
            $beam = BeamClaim::withSingleUseCode($singleUseCode)
                ->with('beam')
                ->first()
                ->beam;
            $code = $beam?->code;
        } else {
            $beam = $this->findByCode($code);
        }

        if (!$beam) {
            throw new BeamException(__('enjin-platform-beam::error.beam_not_found', ['code' => $code]));
        }

        $lock = Cache::lock(self::key($code, 'claim-lock'), 5);

        try {
            $lock->block(5);

            $key = static::key($code);
            if ((int) Cache::get($key, static::claimsCountResolver($code)) < 1) {
                throw new BeamException(__('enjin-platform-beam::error.no_more_claims'));
            }

            ClaimBeam::dispatch($claim = $this->buildClaimBeamData($wallet, $beam, $singleUseCode));
            event(new BeamClaimPending($claim));
            Cache::decrement($key);
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
        return static::CACHE_PREFIX . $name . ($suffix ? ":{$suffix}" : '');
    }

    /**
     * Expire single use codes.
     */
    public function expireSingleUseCodes(array $codes): int
    {
        $beams = [];
        collect($codes)->each(function ($code) use (&$beams) {
            if ($claim = BeamClaim::claimable()->withSingleUseCode($code)->first()) {
                if (!isset($beams[$claim->beam_id])) {
                    $beams[$claim->beam_id] = 0;
                }
                $beams[$claim->beam_id] += $claim->increment('nonce');
            }
        });

        if ($beams) {
            Beam::findMany(array_keys($beams), ['id', 'code'])
                ->each(fn ($beam) => Cache::decrement($this->key($beam->code, $beams[$beam->id])));
        }

        return array_sum($beams);
    }

    /**
     * Check if beam code is single use.
     */
    public static function hasSingleUse(?string $code): bool
    {
        if (!$code) {
            return false;
        }

        return (bool) BeamServiceFacade::findByCode($code)?->hasFlag(BeamFlag::SINGLE_USE);
    }

    /**
     * Check if code is an encrypted single use.
     */
    public static function isSingleUse(?string $code): bool
    {
        if (!$code) {
            return false;
        }

        try {
            decrypt($code);

            return true;
        } catch (Throwable) {
        }

        return false;
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
     * Remove tokens from a beam.
     */
    public function removeTokens(string $code, array $tokens): bool
    {
        $integers = collect($tokens)->filter(fn ($val) => false === $this->integerRange($val))->all();
        if ($integers) {
            Cache::decrement(
                self::key($code),
                BeamClaim::whereIn('token_chain_id', $integers)
                    ->whereHas('beam', fn ($query) => $query->where('code', $code))
                    ->whereNull('claimed_at')
                    ->delete()
            );
        }

        $ranges = collect($tokens)->filter(fn ($val) => false !== $this->integerRange($val))->all();
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
            TokensRemoved::dispatch(['code'=>$code, 'tokenIds' => $tokens]);
        }

        return true;
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

            DispatchCreateBeamClaimsJobs::dispatch($beam, $tokenIds->all())->afterCommit();
        }

        $tokenUploads = $tokens->whereNotNull('tokenIdDataUpload');
        if ($tokenUploads->count()) {
            $ids = $tokenIds->pluck('tokenIds');
            $tokenUploads->each(function ($token) use ($beam, $ids, &$totalClaimCount) {
                LazyCollection::make(function () use ($token, $ids) {
                    $handle = fopen($token['tokenIdDataUpload']->getPathname(), 'r');
                    while (($line = fgets($handle)) !== false) {
                        if (!$this->tokenIdExists($ids->all(), $tokenId = trim($line))) {
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
    protected function buildClaimBeamData(string $wallet, Model $beam, ?string $singleUseCode = null): array
    {
        return array_merge(
            $this->buildRequiredClaimBeamData($wallet, $beam, $singleUseCode),
            ['extras' => $this->buildExtrasClaimBeamData($wallet, $beam)]
        );
    }

    /**
     * Build claim default data.
     */
    protected function buildRequiredClaimBeamData(string $wallet, Model $beam, ?string $singleUseCode = null): array
    {
        return [
            'wallet_public_key' => SS58Address::getPublicKey($wallet),
            'claimed_at' => now(),
            'state' => ClaimStatus::PENDING->name,
            'beam' => $beam->toArray(),
            'beam_id' => $beam->id,
            'ip_address' => request()->getClientIp(),
            'code' => $singleUseCode,
            'idempotency_key' => Str::uuid()->toString(),
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
