<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Models\BeamPack;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\ValidationRule;

class SingleUseCodeExist implements ValidationRule
{
    public function __construct(protected bool $isClaiming = false) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $beamCode = BeamService::getSingleUseCodeData($value)?->beamCode;
        if ($beamCode && ($beam = Beam::where('code', $beamCode)->first())) {
            if (($beam->is_pack ? new BeamPack() : new BeamClaim())
                ->withSingleUseCode($value)
                ->when($this->isClaiming, fn ($query) => $query->claimable())
                ->exists()) {
                return;
            }
        }

        $fail('enjin-platform-beam::validation.verify_signed_message')->translate();
    }
}
