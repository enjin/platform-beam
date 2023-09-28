<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class TokensExistInBeam implements DataAwareRule, ValidationRule
{
    use IntegerRange;
    use HasDataAwareRule;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
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
                    $fail($this->message());

                    return;
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
                    $fail($this->message());
                }
            }
        }
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
