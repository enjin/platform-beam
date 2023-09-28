<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\ValidationRule;

class NotPaused implements ValidationRule
{
    public function __construct(protected ?string $code = null)
    {
    }

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
        if ($beam = resolve(BeamService::class)->findByCode($this->code ?: $value)) {
            if ($beam->hasFlag(BeamFlag::PAUSED)) {
                $fail(__('enjin-platform-beam::validation.is_paused'));
            }
        }
    }
}
