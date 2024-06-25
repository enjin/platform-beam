<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Contracts\Validation\ValidationRule;

class HasBeamFlag implements ValidationRule
{
    /**
     * Create new rule instance.
     */
    public function __construct(protected BeamFlag $flag) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $beam = Beam::whereCode($value)->first();

        if (! $beam || ! $beam->hasFlag($this->flag)) {
            $fail('enjin-platform-beam::validation.has_beam_flag')->translate();
        }
    }
}
