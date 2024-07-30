<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class TokensExistInBeamPack implements DataAwareRule, ValidationRule
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
        $parts = explode('.', $attribute);
        $key = "packs.{$parts[1]}.id";
        if ($id = Arr::get($this->data, $key)) {
            [$integers, $ranges] = collect($value)->partition(fn ($val) => $this->integerRange($val) === false);
            if (count($integers)) {
                $count = BeamClaim::whereIn('token_chain_id', $integers)
                    ->whereNull('claimed_at')
                    ->where('beam_pack_id', $id)
                    ->count();
                if ($count != count($integers)) {
                    $fail($this->message())->translate();

                    return;
                }
            }
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                $count = BeamClaim::whereBetween('token_chain_id', [(int) $from, (int) $to])
                    ->whereNull('claimed_at')
                    ->where('beam_pack_id', $id)
                    ->count();
                if ($count !== ($to - $from) + 1) {
                    $fail($this->message())->translate();
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
        return 'enjin-platform-beam::validation.tokens_exist_in_beam_pack';
    }
}
