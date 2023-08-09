<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\SerializableClosure\Support\ReflectionClosure;

class PassesClaimConditions implements DataAwareRule, ValidationRule
{
    protected static array $functions = [];

    protected array $data = [];

    /**
     * Create new rule instance.
     */
    public function __construct(
        protected bool $singleUse
    ) {
    }

    public static function addConditionalFunctions(Closure|array|Collection $functions): void
    {
        if ($functions instanceof Closure) {
            $functions = Arr::wrap($functions);
        } elseif ($functions instanceof Collection) {
            $functions = $functions->toArray();
        }

        static::$functions = array_merge(static::$functions, $functions);
    }

    public static function getConditionalFunctions(): array
    {
        return static::$functions;
    }

    public static function removeConditionalFunctions(Closure|array|Collection $functions): void
    {
        if ($functions instanceof Closure) {
            $functions = Arr::wrap($functions);
        } elseif ($functions instanceof Collection) {
            $functions = $functions->toArray();
        }

        $staticFunctions = collect(static::$functions)->mapWithKeys(function ($function) {
            $hash = sha1((new ReflectionClosure($function))->getCode());

            return [$hash => $function];
        });

        $diffFunctions = collect($functions)->mapWithKeys(function ($function) {
            $hash = sha1((new ReflectionClosure($function))->getCode());

            return [$hash => $function];
        });

        static::$functions = $staticFunctions->diffKeys($diffFunctions)->values()->toArray();
    }

    public static function clearConditionalFunctions(): void
    {
        static::$functions = [];
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.passes_conditions');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conditions = collect(static::$functions);

        if (!$conditions->every(fn ($function) => $function($attribute, $value, $this->singleUse, $this->data))) {
            $fail(__('enjin-platform-beam::validation.passes_conditions'));
        }
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }
}
