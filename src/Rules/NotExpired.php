<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Contracts\Validation\ValidationRule;

class NotExpired implements ValidationRule
{
    public function __construct(protected ?string $code = null)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$beam = Beam::where('code', $this->code ?: $value)->first()) {
            $fail(__('validation.exists'));

            return;
        }

        if (Carbon::parse($beam->end)->isPast()) {
            $fail(__('enjin-platform-beam::validation.not_expired'));
        }
    }
}
