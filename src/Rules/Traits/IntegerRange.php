<?php

namespace Enjin\Platform\Beam\Rules\Traits;

trait IntegerRange
{
    /**
     * Get the range from and to values.
     */
    protected function integerRange(string $value): bool|array
    {
        if (preg_match('/-?[0-9]+(\.\.)-?[0-9]+/', $value)) {
            return explode('..', $value, 2);
        }

        return false;
    }

    /**
     * Check if tokenId exists from the given tokenIds.
     */
    protected function tokenIdExists(array $tokenIds, string $value): bool
    {
        $integers = array_filter($tokenIds, fn ($val) => $this->integerRange($val) === false);
        $integerRanges = array_filter($tokenIds, fn ($val) => $this->integerRange($val) !== false);

        $valueRange = $this->integerRange($value);
        if ($valueRange === false) {
            if (in_array($value, $integers)) {
                return true;
            }
            foreach ($integerRanges as $range) {
                [$from, $to] = $this->integerRange($range);
                if ($from <= $value && $value <= $to) {
                    return true;
                }
            }
        } else {
            foreach ($integers as $integer) {
                if ($valueRange[0] >= $integer && $valueRange[0] <= $integer) {
                    return true;
                }
            }

            foreach ($integerRanges as $range) {
                [$from, $to] = $this->integerRange($range);
                if ((($from <= $valueRange[0] && $valueRange[0] <= $to) || ($from <= $valueRange[1] && $valueRange[1] <= $to))
                    || (($valueRange[0] <= $from && $from <= $valueRange[1]) || ($valueRange[0] <= $to && $to <= $valueRange[1]))) {
                    return true;
                }
            }
        }

        return false;
    }
}
