<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CanClaim implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Create new rule instance.
     */
    public function __construct(protected bool $singleUse = false)
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Arr::get($this->data, 'account')) {
            return;
        }

        if ($this->singleUse) {
            $value = explode(':', decrypt($value), 3)[1];
        } elseif (BeamService::hasSingleUse($value)) {
            $fail('enjin-platform-beam::validation.can_claim')->translate();

            return;
        }

        $passes = ((int) Cache::get(BeamService::key($value), BeamService::claimsCountResolver($value))) > 0;

        if (! $passes) {
            $fail('enjin-platform-beam::validation.can_claim')->translate();
        }
    }
}
