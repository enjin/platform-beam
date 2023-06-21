<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class TokensExistInBeam implements DataAwareRule, Rule
{
    use IntegerRange;
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
        if ($code = $this->data['code']) {
            $integers = collect($value)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                $count = BeamClaim::whereIn('token_chain_id', $integers)
                    ->whereNull('claimed_at')
                    ->distinct('token_chain_id')
                    ->whereHas('beam', fn ($query) => $query->where('code', $code))
                    ->count();
                if ($count != count($integers)) {
                    return false;
                }
            }
            $ranges = collect($value)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                $count = BeamClaim::whereBetween('token_chain_id', [(int) $from, (int) $to])
                    ->whereNull('claimed_at')
                    ->distinct('token_chain_id')
                    ->whereHas('beam', fn ($query) => $query->where('code', $code))
                    ->count();
                if ($count !== ($to - $from) + 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.tokens_exist_in_beam');
    }
}
