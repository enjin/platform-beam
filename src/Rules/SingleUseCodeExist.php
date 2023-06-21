<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\Rule;

class SingleUseCodeExist implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (BeamService::isSingleUse($value)) {
            return BeamClaim::withSingleUseCode($value)
                ->claimable()
                ->first();
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.verify_signed_message');
    }
}
