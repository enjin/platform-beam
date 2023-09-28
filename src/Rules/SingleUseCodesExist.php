<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;

class SingleUseCodesExist extends SingleUseCodeExist
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
        foreach ($value as $code) {
            parent::validate($attribute, $code, $fail);
        }
    }
}
