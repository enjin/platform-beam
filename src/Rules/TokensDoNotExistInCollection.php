<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Token;
use Illuminate\Contracts\Validation\Rule;

class TokensDoNotExistInCollection implements Rule
{
    use IntegerRange;

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
        if ($this->collectionId) {
            $integers = collect($value)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                $exists = Token::whereIn('token_chain_id', $integers)
                    ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                    ->exists();
                if ($exists) {
                    return false;
                }
            }
            $ranges = collect($value)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                $exists = Token::whereBetween('token_chain_id', [(int) $from, (int) $to])
                    ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                    ->exists();

                if ($exists) {
                    return false;
                }
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
        return __('enjin-platform-beam::validation.tokens_doesnt_exist_in_collection');
    }
}
