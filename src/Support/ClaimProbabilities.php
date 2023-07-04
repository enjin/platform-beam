<?php

namespace Enjin\Platform\Beam\Support;

use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ClaimProbabilities
{
    /**
     * Create or update the probabilities for a claim.
     */
    public function createOrUpdateProbabilities(string $code, array $claims): void
    {
        $current = $this->getProbabilities($code);
        $this->computeProbabilities(
            $code,
            collect($this->filterClaims($claims))
                ->map(fn ($quantity, $tokenId) => Arr::get($current, "tokens.ft.{$tokenId}", 0) + $quantity)
                ->all(),
            collect($this->filterClaims($claims, true))
                ->map(fn ($quantity, $tokenId) => Arr::get($current, "tokens.nft.{$tokenId}", 0) + $quantity)
                ->all(),
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
        $current = $this->getProbabilities($code);
        foreach ($current['tokens'] as $items) {
            foreach ($items['ft'] as $tokenId => $quantity) {
                if (in_array($tokenId, $tokenIds)) {
                    unset($current['tokens']['ft'][$tokenId]);
                }
            }

            foreach ($items['nft'] as $tokenId => $quantity) {
                if (in_array($tokenId, $tokenIds)) {
                    unset($current['tokens']['nft'][$tokenId]);
                }
            }
        }

        $this->computeProbabilities(
            $code,
            Arr::get($current, 'tokens.ft'),
            Arr::get($current, 'tokens.nft'),
        );
    }

    /**
     * Draw a claim base from the probabilities.
     */
    public function drawClaim(string $code): ?Model
    {
        $current = $this->getProbabilities($code);
        $beam = Beam::where('code', $code)->firstOrfail();
        $claim = null;
        $tries = 0;
        $tryLimit = 10;
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
                        $claim = BeamClaim::where('beam_id', $beam->id)
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
        foreach ($fts as $key => $quantity) {
            $probabilities[$key] = ($quantity / $total) * 100;
        }
        $probabilities['nft'] = ($totalNft / $total) * 100;
        asort($probabilities);


        Cache::forever(
            PlatformBeamCache::CLAIM_PROBABILITIES->key($code),
            [
                'tokens' => ['ft' => $fts, 'nft' => $nfts],
                'probabilities' => $probabilities,
            ]
        );
    }
}
