<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\ValidationRule;

class SingleUseCodeExist implements ValidationRule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (BeamService::isSingleUse($value) && null !== BeamClaim::withSingleUseCode($value)->claimable()->first()) {
            return;
        }

        $fail(__('enjin-platform-beam::validation.verify_signed_message'));
    }
}
