<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class PassesClaimCondition implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Create new rule instance.
     */
    public function __construct(
        protected Closure $function,
        protected bool $singleUse
    ) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = ($this->function)($attribute, $value, $this->singleUse, $this->data);

        if ($result !== true) {
            $fail(is_string($result) ? $result : __('enjin-platform-beam::validation.passes_condition'));
        }
    }
}
