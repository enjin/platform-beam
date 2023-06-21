<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Support\SS58Address;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class NotOwner implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    public function __construct(protected bool $isSingleUse = false)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($code = Arr::get($this->data, 'code')) {
            $beam = $this->isSingleUse
               ? BeamClaim::withSingleUseCode($code)
                   ->claimable()
                   ->with('beam.collection.owner')
                   ->first()
               : Beam::with('collection.owner')->where('code', $code)->first();
            if ($beam?->collection?->owner && SS58Address::isSameAddress($value, $beam?->collection?->owner?->public_key)) {
                $fail(__('enjin-platform-beam::validation.not_owner'));
            }
        }
    }
}
