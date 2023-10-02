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
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (BeamService::isSingleUse($value) && BeamClaim::withSingleUseCode($value)->claimable()->exists()) {
            return;
        }

        $fail('enjin-platform-beam::validation.verify_signed_message')->translate();
    }
}
