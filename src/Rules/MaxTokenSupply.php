<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Models\Collection;
use Illuminate\Contracts\Validation\Rule;

class MaxTokenSupply implements Rule
{
    /**
     * The max token supply limit.
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
        if ($this->collectionId && ($collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]))) {
            if (!is_null($this->limit = $collection->max_token_supply)) {
                return $collection->max_token_supply >= $value;
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
        return __('enjin-platform-beam::validation.max_token_supply', ['limit' => $this->limit]);
    }
}
