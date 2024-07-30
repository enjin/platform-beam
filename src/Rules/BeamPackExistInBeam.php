<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class BeamPackExistInBeam implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;
    use IntegerRange;

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!filled($value)) {
            return;
        }

        if (!Beam::where('code', $this->data['code'])->whereHas('packs', fn ($query) => $query->where('id', $value))->exists()) {
            $fail('enjin-platform-beam::validation.beam_pack_exist_in_beam')->translate();
        }

    }
}
