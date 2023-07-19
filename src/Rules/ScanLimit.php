<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Models\BeamScan;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class ScanLimit implements DataAwareRule, Rule
{
    use HasDataAwareRule;

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
        if (!$limit = config('enjin-platform-beam.scan_limit')) {
            return true;
        }

        return $limit > (int) BeamScan::whereWalletPublicKey($value)
            ->hasCode($this->data['code'])
            ->first()?->count;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.scan_limit');
    }
}
