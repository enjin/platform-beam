<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CanClaim implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Create new rule instance.
     */
    public function __construct(protected bool $singleUse = false)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Arr::get($this->data, 'account')) {
            return;
        }

        if ($this->singleUse) {
            $value = explode(':', decrypt($value), 3)[1];
        }

        $passes = ((int) Cache::get(BeamService::key($value), BeamService::claimsCountResolver($value))) > 0;

        if (!$passes) {
            $fail(__('enjin-platform-beam::validation.can_claim'));
        }
    }
}
