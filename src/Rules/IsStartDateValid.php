<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class IsStartDateValid implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     *
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($end = Arr::get($this->data, 'end')) {
            if (Carbon::parse($value)->gte(Carbon::parse($end))) {
                $fail(__('enjin-platform-beam::validation.start_date_after_end'));

                return;
            }
        }

        if ($beam = resolve(BeamService::class)->findByCode($this->data['code'])) {
            if (Carbon::parse($beam->start)->isPast()) {
                $fail(__('enjin-platform-beam::validation.start_date_has_passed'));

                return;
            }
            $endDate = Carbon::parse($beam->end);
            if (Carbon::parse($value)->gte($endDate)) {
                $fail('enjin-platform-beam::validation.start_date_less_than')
                    ->translate([
                        'value' => $endDate->toDateTimeString(),
                    ]);
            }
        }
    }
}
