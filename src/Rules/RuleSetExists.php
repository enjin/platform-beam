<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\FuelTanks\Models\FuelTank;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class RuleSetExists implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($tankId = Arr::get($this->data, 'tankId')) && !FuelTank::whereHas(
            'dispatchRules',
            fn ($query) => $query->where('rule_set_id', $value)
        )->where('public_key', SS58Address::getPublicKey($tankId))->exists()
        ) {
            $fail('enjin-platform-fuel-tanks::validation.rule_set_not_exist')->translate();
        }
    }
}
