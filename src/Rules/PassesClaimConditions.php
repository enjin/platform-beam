<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\SerializableClosure\Support\ReflectionClosure;

class PassesClaimConditions implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;

    protected static array $functions = [];

    /**
     * Create new rule instance.
     */
    public function __construct(
        protected bool $singleUse
    ) {}

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
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $conditions = collect(static::$functions);

        if (! $conditions->every(fn ($function) => $function($attribute, $value, $this->singleUse, $this->data))) {
            $fail('enjin-platform-beam::validation.passes_conditions')->translate();
        }
    }
}
