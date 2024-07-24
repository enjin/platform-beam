<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class CanUseOnBeamPack implements ValidationRule
{
    public function __construct(protected ?Model $beam = null) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $beam = $this->beam ?: Beam::where('code', $value)->first();
        if (!$beam->is_pack) {
            $fail('enjin-platform-beam::validation.can_use_on_beam_pack')->translate();
        }
    }
}
