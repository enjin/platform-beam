<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamPack;
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
    public function __construct(protected bool $singleUse = false) {}

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
            $value = BeamService::getSingleUseCodeData($value)?->beamCode;
        } elseif (BeamService::hasSingleUse($value)) {
            $fail('enjin-platform-beam::validation.can_claim')->translate();

            return;
        }

        $remaining = (int) Cache::get(BeamService::key($value), BeamService::claimsCountResolver($value));
        if ($beam = Beam::where('code', $value)->with('collection')->first()) {
            if ($beam->collection?->is_frozen) {
                $claimableCount = $beam->is_pack && BeamClaim::claimable()
                    ->where('beam_id', $beam->id)
                    ->where('type', BeamType::MINT_ON_DEMAND->name)
                    ->count() == 0
                    ? BeamPack::claimable()
                        ->where('beam_id', $beam->id)
                        ->count()
                    : BeamClaim::claimable()
                        ->where('beam_id', $beam->id)
                        ->where('type', BeamType::TRANSFER_TOKEN->name)
                        ->count();
                $remaining = $remaining - $claimableCount;
            }
        }

        $passes = $remaining > 0;

        if (! $passes) {
            $fail('enjin-platform-beam::validation.can_claim')->translate();
        }
    }
}
