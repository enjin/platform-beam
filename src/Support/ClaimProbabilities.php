<?php

namespace Enjin\Platform\Beam\Support;

use Closure;
use Enjin\Platform\Beam\Enums\PlatformBeamCache;
use Enjin\Platform\Beam\Models\Laravel\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ClaimProbabilities
{
    use IntegerRange;

    /**
     * Create or update the probabilities for a claim.
     */
    public function createOrUpdateProbabilities(string $code, array $claims): void
    {
        $formatted = $this->filterClaims($claims);
        $current = self::getProbabilities($code);
        $this->computeProbabilities(
            $code,
            $this->mergeTokens(Arr::get($current, 'tokens.ft', []), $formatted['ft']),
            $this->mergeTokens(Arr::get($current, 'tokens.nft', []), $formatted['nft']),
        );
    }

    /**
     * Check if the key has probabilities.
     */
    public static function hasProbabilities(string $code): bool
    {
        return Cache::has(PlatformBeamCache::CLAIM_PROBABILITIES->key($code));
    }

    /**
     * Get the probabilities for a code.
     */
    public static function getProbabilities(string $code): array
    {
        return Cache::get(
            PlatformBeamCache::CLAIM_PROBABILITIES->key($code),
            static::getProbabilitiesFromDB($code)
        );
    }

    /**
     * Get the probabilities from the database.
     */
    public static function getProbabilitiesFromDB(string $code): Closure
    {
        return function () use ($code) {
            $claims = BeamClaim::selectRaw('
                    token_chain_id,
                    quantity as tokenQuantityPerClaim,
                    count(*) as claimQuantity
                ')->hasCode($code)
                ->groupBy('token_chain_id', 'quantity')
                ->get()
                ->map(function ($claim) {
                    $claim->tokenIds = [$claim->token_chain_id];

                    return $claim;
                })->toArray();

            $instance = resolve(static::class);
            $formatted = $instance->filterClaims($claims);
            $instance->computeProbabilities(
                $code,
                $formatted['ft'],
                $formatted['nft'],
            );

            return Cache::get(PlatformBeamCache::CLAIM_PROBABILITIES->key($code), []);
        };
    }

    /**
     * Remove tokens from the probabilities.
     */
    public function removeTokens(string $code, array $tokenIds): void
    {
        if ($current = self::getProbabilities($code)) {
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
     * Compute the probabilities for the items.
     */
    public function computeProbabilities(string $code, array $fts, array $nfts): void
    {
        $total = collect($fts)->sum() + ($totalNft = collect($nfts)->sum());
        if (!$total) {
            return;
        }

        $probabilities = [
            'ft' => collect($fts)->mapWithKeys(fn ($quantity, $key) => [$key => ($quantity / $total) * 100])->toArray(),
            'nft' => ($totalNft / $total) * 100,
            'ftTokenIds' => $this->extractTokenIds($fts, $total),
            'nftTokenIds' => $this->extractTokenIds($nfts, $total),
        ];

        $data = [
            'tokens' => ['ft' => $fts, 'nft' => $nfts],
            'probabilities' => $probabilities,
        ];

        Cache::forever(
            PlatformBeamCache::CLAIM_PROBABILITIES->key($code),
            $data
        );
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
     * Format claims into a token => quantity array.
     */
    protected function formatClaims(Collection $claims): array
    {
        $tokens = [];
        foreach ($claims as $claim) {
            foreach ($claim['tokenIds'] as $tokenId) {
                $range = $this->integerRange($tokenId);
                $tokens[$tokenId] = $range !== false
                    ? (($range[1] - $range[0]) + 1) * $claim['claimQuantity']
                    : $claim['claimQuantity'];
            }
        }

        return $tokens;
    }

    /**
     * Filter the claims for FTs and NFTs.
     */
    protected function filterClaims(array $claims): array
    {
        [$nfts, $fts] = collect($claims)->partition(
            fn ($claim) => $claim['claimQuantity'] == 1 && Arr::get($claim, 'tokenQuantityPerClaim', 1) == 1
        );

        return [
            'ft' => $this->formatClaims($fts),
            'nft' => $this->formatClaims($nfts),
        ];
    }

    /**
     * Extract the token ids from the array.
     */
    protected function extractTokenIds(array $tokenIds, int $total): array
    {
        $tokens = [];
        foreach ($tokenIds as $key => $quantity) {
            if (($range = $this->integerRange($key)) !== false) {
                $count = $quantity / (($range[1] - $range[0]) + 1);
                for ($i = $range[0]; $i <= $range[1]; $i++) {
                    $tokens[$i] = ($count / $total) * 100;
                }
            } else {
                $tokens[$key] = ($quantity / $total) * 100;
            }
        }

        return $tokens;
    }
}
