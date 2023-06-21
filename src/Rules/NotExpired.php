<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Contracts\Validation\ValidationRule;

class NotExpired implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$beam = Beam::where('code', $value)->first()) {
            $fail(__('validation.exists'));

            return;
        }

        if (Carbon::parse($beam->end)->isPast()) {
            $fail(__('enjin-platform-beam::validation.not_expired'));
        }
    }
}
