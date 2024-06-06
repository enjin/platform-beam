<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class IsEndDateValid implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = Carbon::parse($value);
        $start = Arr::get($this->data, 'start');
        if ($start && $date->lte(Carbon::parse($start))) {
            $fail('enjin-platform-beam::validation.end_date_after_start')->translate();

            return;
        }

        if ($beam = resolve(BeamService::class)->findByCode($this->data['code'])) {
            $startDate = Carbon::parse($beam->start);
            if ($date->lte($startDate)) {
                $fail('enjin-platform-beam::validation.end_date_greater_than')
                    ->translate([
                        'value' => $startDate->toDateTimeString(),
                    ]);
            }
        }
    }
}
