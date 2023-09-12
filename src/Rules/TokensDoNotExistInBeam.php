<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TokensDoNotExistInBeam implements DataAwareRule, Rule
{
    use IntegerRange;
    use HasDataAwareRule;

    public function __construct(protected ?Model $beam = null)
    {
    }

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
        $prepare = static::prepareStatement($this->beam, Arr::get($this->data, 'collectionId'));
        $integers = collect($value)->filter(fn ($val) => false === $this->integerRange($val))->all();
        if ($integers) {
            if ($prepare->whereIn('beam_claims.token_chain_id', $integers)->exists()) {
                return false;
            }
        }
        $ranges = collect($value)->filter(fn ($val) => false !== $this->integerRange($val))->all();
        foreach ($ranges as $range) {
            [$from, $to] = $this->integerRange($range);
            if ($prepare->whereBetween('beam_claims.token_chain_id', [(int) $from, (int) $to])->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepare the statement to check if the token ids exist in the beam.
     */
    public static function prepareStatement(?Model $beam, ?string $collectionId = null): Builder
    {
        $collectionId = Arr::get($beam, 'collection_chain_id', $collectionId);

        return BeamClaim::whereHas('beam', fn ($query) => $query->where('end', '>', now()))
            ->join(
                'collections',
                fn ($join) => $join->on('collections.id', '=', 'beam_claims.collection_id')
                    ->when(
                        $collectionId,
                        fn ($query) => $query->where('collections.collection_chain_id', $collectionId)
                    )
            )->leftJoin(
                'tokens',
                fn ($join) => $join->on('tokens.token_chain_id', '=', 'beam_claims.token_chain_id')
                    ->whereColumn('tokens.collection_id', 'beam_claims.collection_id')
            )->whereRaw("
                (tokens.is_currency is false OR tokens.is_currency is NULL)
                AND (
                    collections.max_token_supply = '1'
                    OR (collections.force_single_mint is true AND tokens.supply = '1')
                    OR (tokens.cap='" . TokenMintCapType::SUPPLY->name . "' AND tokens.cap_supply = '1')
                    OR (tokens.cap='" . TokenMintCapType::SINGLE_MINT->name . "' AND tokens.supply = '1')
                )
            ")->when($beam, fn ($query) => $query->where('beam_claims.beam_id', $beam->id));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('enjin-platform-beam::validation.tokens_doesnt_exist_in_beam');
    }
}
