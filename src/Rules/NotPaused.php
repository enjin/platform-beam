<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\Rule;

class NotPaused implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes(mixed $attribute, mixed $value)
    {
        if ($beam = resolve(BeamService::class)->findByCode($value)) {
            if ($beam->hasFlag(BeamFlag::PAUSED)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): array|string
    {
        return __('enjin-platform-beam::validation.is_paused');
    }
}
