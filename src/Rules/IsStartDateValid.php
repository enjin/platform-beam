<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class IsStartDateValid implements DataAwareRule, Rule
{
    use HasDataAwareRule;

    /**
     * The error message.
     */
    protected string $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($end = Arr::get($this->data, 'end')) {
            if (Carbon::parse($value)->gte(Carbon::parse($end))) {
                $this->message = __('enjin-platform-beam::validation.start_date_after_end');

                return false;
            }
        }

        if ($beam = resolve(BeamService::class)->findByCode($this->data['code'])) {
            if (Carbon::parse($beam->start)->isPast()) {
                $this->message = __('enjin-platform-beam::validation.start_date_has_passed');

                return false;
            }
            $endDate = Carbon::parse($beam->end);
            if (Carbon::parse($value)->gte($endDate)) {
                $this->message = __('enjin-platform-beam::validation.start_date_less_than', ['value' => $endDate->toDateTimeString()]);

                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
