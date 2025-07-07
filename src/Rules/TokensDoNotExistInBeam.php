<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TokensDoNotExistInBeam implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;
    use IntegerRange;

    public function __construct(protected ?Model $beam = null) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $prepare = static::prepareStatement($this->beam, Arr::get($this->data, 'collectionId'));
        $integers = collect($value)->filter(fn ($val) => $this->integerRange($val) === false)->all();

        if ($integers) {
            if ($prepare->whereIn('beam_claims.token_chain_id', $integers)->exists()) {
                $fail($this->message())->translate();

                return;
            }
        }
        $ranges = collect($value)->filter(fn ($val) => $this->integerRange($val) !== false)->all();
        foreach ($ranges as $range) {
            [$from, $to] = $this->integerRange($range);
            if ($prepare->whereBetween('beam_claims.token_chain_id', [(int) $from, (int) $to])->exists()) {
                $fail($this->message())->translate();
            }
        }
    }

    /**
     * Prepare the statement to check if the token ids exist in the beam.
     */
    public static function prepareStatement(?Model $beam, ?string $collectionId = null): Builder
    {
        return BeamClaim::query()
            ->whereHas('beam', fn ($query) => $query->where('end', '>', now()))
            ->join(
                'collections',
                fn ($join) => $join->on('collections.id', '=', 'beam_claims.collection_id')
                    ->when(
                        $collectionId = Arr::get($beam, 'collection_chain_id', $collectionId),
                        fn ($query) => $query->where('collections.collection_chain_id', $collectionId)
                    )
            )->leftJoin(
                'tokens',
                fn ($join) => $join->on('tokens.token_chain_id', '=', 'beam_claims.token_chain_id')
                    ->whereColumn('tokens.collection_id', 'beam_claims.collection_id')
            )->whereNull('beam_claims.deleted_at')
            ->whereRaw("
                (tokens.is_currency is false OR tokens.is_currency is NULL)
                AND (
                    collections.max_token_supply = '1'
                    OR (collections.force_collapsing_supply is true AND tokens.supply = '1')
                    OR (tokens.cap='" . TokenMintCapType::SUPPLY->name . "' AND tokens.cap_supply = '1')
                    OR (tokens.cap='" . TokenMintCapType::COLLAPSING_SUPPLY->name . "' AND tokens.cap_supply = '1')
                )
            ")->when($beam, fn ($query) => $query->where('beam_claims.beam_id', $beam->id));
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'enjin-platform-beam::validation.tokens_doesnt_exist_in_beam';
    }
}
