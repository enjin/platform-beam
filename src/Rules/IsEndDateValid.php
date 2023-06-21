<?php

namespace Enjin\Platform\Beam\Rules;

use Carbon\Carbon;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Services\BeamService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class IsEndDateValid implements DataAwareRule, Rule
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
        $date = Carbon::parse($value);
        if ($start = Arr::get($this->data, 'start')) {
            if ($date->lte(Carbon::parse($start))) {
                $this->message = __('enjin-platform-beam::validation.end_date_after_start');

                return false;
            }
        } elseif ($beam = resolve(BeamService::class)->findByCode($this->data['code'])) {
            $startDate = Carbon::parse($beam->start);
            if ($date->lte($startDate)) {
                $this->message = __('enjin-platform-beam::validation.end_date_greater_than', ['value' => $startDate->toDateTimeString()]);

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
