<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\Token;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

class MaxTokenCount implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;
    use IntegerRange;

    /**
     * The max token count limit.
     *
     * @var int
     */
    protected $limit;

    public function __construct(protected ?string $collectionId) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /**
         * The sum of all unique tokens (including existing tokens, tokens in beams, and tokens to be created)
         * must not exceed the collection's maximum token count.
         */
        if ($this->collectionId
            && ($collection = Collection::withCount('tokens')->firstWhere(['collection_chain_id' => $this->collectionId]))
            && ! is_null($this->limit = $collection->max_token_count)
        ) {
            if ($this->limit == 0) {
                $fail('enjin-platform-beam::validation.max_token_count')->translate(['limit' => $this->limit]);

                return;
            }

            $claimCount = BeamClaim::where('type', BeamType::MINT_ON_DEMAND->name)
                ->whereHas(
                    'beam',
                    fn ($query) => $query->where('collection_chain_id', $this->collectionId)->where('end', '>', now())
                )->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('tokens')
                        ->whereColumn('tokens.token_chain_id', 'beam_claims.token_chain_id');
                })
                ->groupBy('token_chain_id')
                ->count();

            $tokens = collect($this->data['tokens'])
                ->filter(fn ($data) => !empty(Arr::get($data, 'tokenIds')))
                ->pluck('tokenIds')
                ->flatten();

            collect($this->data['tokens'])
                ->filter(fn ($data) => !empty(Arr::get($data, 'tokenIdDataUpload')))
                ->map(function ($data) use ($tokens) {
                    $handle = fopen($data['tokenIdDataUpload']->getPathname(), 'r');
                    while (($line = fgets($handle)) !== false) {
                        if (! $this->tokenIdExists($tokens->all(), $tokenId = trim($line))) {
                            $tokens->push($tokenId);
                        }
                    }
                    fclose($handle);
                });

            [$integers, $ranges] = collect($tokens)->unique()->partition(fn ($val) => $this->integerRange($val) === false);

            $createTokenTotal = 0;
            if ($integers->count()) {
                $existingTokens = Token::where('collection_id', $collection->id)
                    ->whereIn('token_chain_id', $integers)
                    ->pluck('token_chain_id');

                $integers = $integers->diff($existingTokens);
                if ($integers->count()) {
                    $existingClaimsCount = BeamClaim::where('collection_id', $collection->id)
                        ->whereIn('token_chain_id', $integers)
                        ->claimable()
                        ->pluck('token_chain_id');

                    $createTokenTotal = $integers->diff($existingClaimsCount)->count();
                }
            }

            if ($ranges->count()) {
                foreach ($ranges as $range) {
                    [$from, $to] = $this->integerRange($range);
                    $existingTokensCount = Token::where('collection_id', $collection->id)
                        ->whereBetween('token_chain_id', [(int) $from, (int) $to])
                        ->count();

                    if (($to - $from) + 1 == $existingTokensCount) {
                        continue;
                    }

                    LazyCollection::range((int) $from, (int) $to)
                        ->chunk(5000)
                        ->each(function ($chunk) use (&$createTokenTotal, $collection) {
                            $existingTokens = Token::where('collection_id', $collection->id)
                                ->whereIn('token_chain_id', $chunk)
                                ->pluck('token_chain_id');

                            $integers = $chunk->diff($existingTokens);
                            if ($integers->count()) {
                                $existingClaimsCount = BeamClaim::where('collection_id', $collection->id)
                                    ->whereIn('token_chain_id', $integers)
                                    ->claimable()
                                    ->pluck('token_chain_id');
                                $createTokenTotal += $integers->diff($existingClaimsCount)->count();
                            }
                        });
                }
            }

            $createTokenTotal = $createTokenTotal > 0 ? $createTokenTotal : 0;
            if ($collection->max_token_count < $collection->tokens_count + $claimCount + $createTokenTotal) {
                $fail('enjin-platform-beam::validation.max_token_count')->translate(['limit' => $this->limit]);
            }
        }
    }
}
