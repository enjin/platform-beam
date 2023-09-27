<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class PassesClaimCondition implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    /**
     * Create new rule instance.
     */
    public function __construct(
        protected Closure $function,
        protected bool $singleUse
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = ($this->function)($attribute, $value, $this->singleUse, $this->data);

        if (true !== $result) {
            $fail(is_string($result) ? $result : __('enjin-platform-beam::validation.passes_condition'));
        }
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }
}
