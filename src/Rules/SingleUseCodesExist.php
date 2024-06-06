<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;

class SingleUseCodesExist extends SingleUseCodeExist
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($value as $code) {
            parent::validate($attribute, $code, $fail);
        }
    }
}
