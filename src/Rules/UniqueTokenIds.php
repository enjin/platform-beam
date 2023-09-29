<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueTokenIds implements ValidationRule
{
    use IntegerRange;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tokenIds = [];
        $tokens = collect($value)
            ->pluck('tokenIds')
            ->filter()
            ->flatten()
            ->sortBy(fn ($tokenId) => false !== $this->integerRange($tokenId));

        foreach ($tokens->all() as $tokenId) {
            if ($this->tokenIdExists($tokenIds, $tokenId)) {
                $fail('enjin-platform-beam::validation.duplicate_token_ids')->translate();

                return;
            }
            $tokenIds[] = $tokenId;
        }
    }
}
