<?php

namespace Enjin\Platform\Beam\Support;

use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClaimProbabilities
{
    use IntegerRange;

    /**
     * Create or update the probabilities for a claim.
     */
    public function createOrUpdateProbabilities(string $code, array $claims): void
    {
        $fts = $this->filterClaims($claims);
        $nfts = $this->filterClaims($claims, true);
        $current = $this->getProbabilities($code);
        $this->computeProbabilities(
            $code,
            $this->mergeTokens(Arr::get($current, 'tokens.ft', []), $fts),
            $this->mergeTokens(Arr::get($current, 'tokens.nft', []), $nfts),
        );
    }

    /**
     * Check if the key has probabilities.
     */
    public function hasProbabilities(string $code): bool
    {
        return Cache::has(PlatformBeamCache::CLAIM_PROBABILITIES->key($code));
    }

    /**
     * Get the probabilities for a code.
     */
    public function getProbabilities(string $code): array
    {
        return Cache::get(PlatformBeamCache::CLAIM_PROBABILITIES->key($code), []);
    }

    /**
     * Remove tokens from the probabilities.
     */
    public function removeTokens(string $code, array $tokenIds): void
    {
        if ($current = $this->getProbabilities($code)) {
            foreach (Arr::get($current, 'tokens.ft', []) as $tokenId => $quantity) {
                if (in_array($tokenId, $tokenIds)) {
                    unset($current['tokens']['ft'][$tokenId]);
                }
            }

            foreach (Arr::get($current, 'tokens.nft', []) as $tokenId => $quantity) {
                if (in_array($tokenId, $tokenIds)) {
                    unset($current['tokens']['nft'][$tokenId]);
                }
            }

            $this->computeProbabilities(
                $code,
                Arr::get($current, 'tokens.ft'),
                Arr::get($current, 'tokens.nft'),
            );
        }
    }

    /**
     * Draw a claim base from the probabilities.
     */
    public function drawClaim(string $code): ?Model
    {
        if (!$current = $this->getProbabilities($code)) {
            return null;
        }

        $beam = Beam::where('code', $code)->firstOrfail();
        $claim = null;
        $tries = 0;
        $tryLimit = count($current['probabilities']);
        do {
            $rand = random_int(1, 100);
            foreach ($current['probabilities'] as $tokenId => $chance) {
                if ($rand <= $chance) {
                    if ($tokenId === 'nft') {
                        $claim = BeamClaim::where('beam_id', $beam->id)
                            ->claimable()
                            ->where('is_nft', true)
                            ->first();
                    } else {
                        $ranges = $this->integerRange($tokenId);
                        $claim = $ranges !== false
                            ? BeamClaim::whereBetween('token_chain_id', [(int) $ranges[0], (int) $ranges[1]])
                                ->claimable()
                                ->inRandomOrder()
                                ->first()
                            : BeamClaim::where('beam_id', $beam->id)
                                ->claimable()
                                ->where('token_chain_id', $tokenId)
                                ->first();
                    }
                }
            }
            $tries++;
        } while (is_null($claim) && $tries <= $tryLimit);

        return $claim;
    }

    /**
     * Merge the tokens into the current tokens.
     */
    protected function mergeTokens(array $current, array $tokens): array
    {
        foreach ($tokens as $tokenId => $quantity) {
            $current[$tokenId] = isset($current[$tokenId])
                ? $current[$tokenId] + $quantity
                : $quantity;
        }

        return $current;
    }

    /**
     * Filter the claims for FTs and NFTs.
     */
    protected function filterClaims(array $claims, bool $isNft = false): array
    {
        $tokens = [];
        foreach ($claims as $claim) {
            if ($claim['isNft'] === $isNft) {
                foreach ($claim['tokenIds'] as $tokenId) {
                    $tokens[$tokenId] = $claim['claimQuantity'];
                }
            }
        }

        return $tokens;
    }

    /**
     * Compute the probabilities for the items.
     */
    protected function computeProbabilities(string $code, array $fts, array $nfts): void
    {
        $totalNft = collect($nfts)->sum();
        $total = collect($fts)->sum() + $totalNft;
        $probabilities = [];
        if ($total > 0) {
            foreach ($fts as $key => $quantity) {
                $probabilities[$key] = ($quantity / $total) * 100;
            }
            $probabilities['nft'] = ($totalNft / $total) * 100;
            asort($probabilities);
        }


        Log::info('Probabilities', [
            'tokens' => ['ft' => $fts, 'nft' => $nfts],
            'probabilities' => $probabilities,
        ]);

        Cache::forever(
            PlatformBeamCache::CLAIM_PROBABILITIES->key($code),
            [
                'tokens' => ['ft' => $fts, 'nft' => $nfts],
                'probabilities' => $probabilities,
            ]
        );
    }
}
