<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

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

    public function __construct(protected ?string $collectionId)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->collectionId && ($collection = Collection::withCount('tokens')->firstWhere(['collection_chain_id' => $this->collectionId]))) {
            if (! is_null($this->limit = $collection->max_token_count)) {
                $passes = $collection->max_token_count >= $collection->tokens_count
                    + collect($this->data['tokens'])
                        ->filter(fn ($token) => BeamType::getEnumCase($token['type']) == BeamType::MINT_ON_DEMAND)
                        ->reduce(function ($carry, $token) {
                            return collect(Arr::get($token, 'tokenIds'))->reduce(function ($val, $tokenId) use ($token) {
                                $range = $this->integerRange($tokenId);

                                return $val + (
                                    $range === false
                                        ? $token['claimQuantity']
                                        : (($range[1] - $range[0]) + 1) * $token['claimQuantity']
                                );
                            }, $carry);
                        }, 0)
                    + BeamClaim::whereHas(
                        'beam',
                        fn ($query) => $query->where('collection_chain_id', $this->collectionId)->where('end', '>', now())
                    )->where('type', BeamType::MINT_ON_DEMAND->name)
                        ->count();

                if (! $passes) {
                    $fail('enjin-platform-beam::validation.max_token_count')
                        ->translate([
                            'limit' => $this->limit,
                        ]);
                }
            }
        }
    }
}
