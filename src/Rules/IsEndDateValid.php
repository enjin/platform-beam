<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Closure;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class IsEndDateValid implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

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
        $date = Carbon::parse($value);
        $start = Arr::get($this->data, 'start');
        if ($start && $date->lte(Carbon::parse($start))) {
            $fail(__('enjin-platform-beam::validation.end_date_after_start'));

            return;
        }

        if ($beam = resolve(BeamService::class)->findByCode($this->data['code'])) {
            $startDate = Carbon::parse($beam->start);
            if ($date->lte($startDate)) {
                $fail(__('enjin-platform-beam::validation.end_date_greater_than', ['value' => $startDate->toDateTimeString()]));
            }
        }
    }
}
