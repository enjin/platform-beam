<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class TokenIdExistsInParams implements DataAwareRule, ValidationRule
{
    use IntegerRange;
    use HasDataAwareRule;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tokens = collect($this->data['tokens'])
            ->pluck('tokenIds')
            ->filter()
            ->flatten()
            ->sortBy(fn ($tokenId) => false === $this->integerRange($tokenId))
            ->all();
        if ($tokens && !$this->tokenIdExists($tokens, $value)) {
            $fail(__('enjin-platform-beam::validation.token_id_exists_in_params'));
        }
    }
}
