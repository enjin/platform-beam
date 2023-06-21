<?php

namespace Enjin\Platform\Beam\Rules;

class SingleUseCodesExist extends SingleUseCodeExist
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
        foreach ($value as $code) {
            if (!parent::passes($attribute, $code)) {
                return false;
            }
        }

        return true;
    }
}
