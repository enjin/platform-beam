<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Models\Collection;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class MaxTokenCount implements DataAwareRule, Rule
{
    use HasDataAwareRule;

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
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->collectionId && ($collection = Collection::withCount('tokens')->firstWhere(['collection_chain_id'=> $this->collectionId]))) {
            if (!is_null($this->limit = $collection->max_token_count)) {
                return $collection->max_token_count >= $collection->tokens_count
                    + collect($this->data['tokens'])
                        ->filter(fn ($token) => BeamType::getEnumCase($token['type']) == BeamType::MINT_ON_DEMAND)
                        ->sum('claimQuantity');
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.max_token_count', ['limit' => $this->limit]);
    }
}
