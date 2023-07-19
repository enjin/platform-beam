<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Rules\MaxBigInt;

class MaxBigIntIntegerRange extends MaxBigInt
{
    use Traits\IntegerRange;

    /**
     * Determine if the value is a valid min big int.
     */
    protected function isValidMaxBigInt($value): bool
    {
        $range = $this->integerRange($value);
        $value = false === $range ? $value : $range[0];
        $this->message = __('enjin-platform::validation.max_big_int', ['max' => $this->max]);

        return bccomp($this->max, $value) >= 0;
    }
}
