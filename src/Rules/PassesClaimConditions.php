<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

class PassesClaimConditions implements DataAwareRule, ValidationRule
{
    public static array $functions = [];

    protected array $data = [];

    /**
     * Create new rule instance.
     */
    public function __construct(
        protected bool $singleUse
    ) {
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.passes_conditions');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conditions = collect(static::$functions);

        if (!$conditions->every(fn ($function) => $function($attribute, $value, $this->singleUse, $this->data))) {
            $fail(__('enjin-platform-beam::validation.passes_conditions'));
        }
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }
}
