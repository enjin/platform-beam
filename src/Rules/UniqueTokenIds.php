<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueTokenIds implements ValidationRule
{
    use IntegerRange;

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
                $fail(__('enjin-platform-beam::validation.duplicate_token_ids'));

                return;
            }
            $tokenIds[] = $tokenId;
        }
    }
}
