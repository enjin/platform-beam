<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Rules\MinBigInt;

class MinBigIntIntegerRange extends MinBigInt
{
    use Traits\IntegerRange;

    /**
     * Determine if the value is a valid min big int.
     */
    protected function isValidMinBigInt($value): bool
    {
        $range = $this->integerRange($value);
        $value = $range === false ? $value : $range[0];
        $this->message = __('enjin-platform::validation.min_big_int', ['min' => $this->min]);

        return bccomp($this->min, $value) <= 0;
    }
}
