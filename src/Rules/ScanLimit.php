<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class ScanLimit implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $limit = config('enjin-platform-beam.scan_limit');

        if ($limit &&
            !($limit > (int) BeamScan::whereWalletPublicKey($value)
                ->hasCode($this->data['code'])
                ->first()?->count)
        ) {
            $fail('enjin-platform-beam::validation.scan_limit')->translate();
        }
    }
}
