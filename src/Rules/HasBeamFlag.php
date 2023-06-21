<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Contracts\Validation\Rule;

class HasBeamFlag implements Rule
{
    /**
     * Create new rule instance.
     */
    public function __construct(protected BeamFlag $flag)
    {
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        if (!$beam = Beam::whereCode($value)->first()) {
            return false;
        }

        return $beam->hasFlag($this->flag);
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.has_beam_flag');
    }
}
