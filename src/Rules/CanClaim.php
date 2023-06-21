<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CanClaim implements DataAwareRule, Rule
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
     */
    public function passes($attribute, $value): bool
    {
        if (!Arr::get($this->data, 'account')) {
            return true;
        }

        if ($this->singleUse) {
            $value = explode(':', decrypt($value), 3)[1];
        }

        return ((int) Cache::get(BeamService::key($value), BeamService::claimsCountResolver($value))) > 0;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('enjin-platform-beam::validation.can_claim');
    }
}
