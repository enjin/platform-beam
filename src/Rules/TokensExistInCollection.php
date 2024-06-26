<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Token;
use Illuminate\Contracts\Validation\ValidationRule;

class TokensExistInCollection implements ValidationRule
{
    use IntegerRange;

    public function __construct(protected ?string $collectionId) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->collectionId) {
            $integers = collect($value)->filter(fn ($val) => $this->integerRange($val) === false)->all();
            if ($integers) {
                $count = Token::whereIn('token_chain_id', $integers)
                    ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                    ->count();
                if ($count !== count($integers)) {
                    $fail($this->message())->translate();

                    return;
                }
            }
            $ranges = collect($value)->filter(fn ($val) => $this->integerRange($val) !== false)->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                $count = Token::whereBetween('token_chain_id', [(int) $from, (int) $to])
                    ->whereHas('collection', fn ($query) => $query->where('collection_chain_id', $this->collectionId))
                    ->count();
                if ($count !== ($to - $from) + 1) {
                    $fail($this->message())->translate();
                }
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'enjin-platform-beam::validation.tokens_exist_in_collection';
    }
}
