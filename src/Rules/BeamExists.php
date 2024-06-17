<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\ValidationRule;

class BeamExists implements ValidationRule
{
    public function __construct(protected string $column = 'code')
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($beamData = BeamService::getSingleUseCodeData($value)) {
            $value = $beamData->beamCode;
        }

        if (!Beam::where($this->column, $value)->exists()) {
            $fail('validation.exists')->translate();
        }
    }
}
