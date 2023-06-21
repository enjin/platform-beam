<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Illuminate\Contracts\Validation\Rule;

class TokensDoNotExistInBeam implements Rule
{
    use IntegerRange;

    public function __construct(protected ?string $collectionId)
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
        if ($this->collectionId) {
            $integers = collect($value)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                $exists = BeamClaim::whereIn('beam_claims.token_chain_id', $integers)
                    ->leftJoin(
                        'tokens',
                        fn ($join) => $join->on('tokens.token_chain_id', '=', 'beam_claims.token_chain_id')
                            ->whereColumn('tokens.collection_id', 'beam_claims.collection_id')
                    )
                    ->join(
                        'collections',
                        fn ($join) => $join->on('collections.id', '=', 'beam_claims.collection_id')
                            ->where('collections.collection_chain_id', $this->collectionId)
                    )
                    ->whereRaw("
                        (tokens.is_currency is false OR tokens.is_currency is NULL)
                        AND (
                            collections.max_token_supply = '1'
                            OR (collections.force_single_mint is true AND tokens.supply = '1')
                            OR (tokens.cap='" . TokenMintCapType::SUPPLY->name . "' AND tokens.cap_supply = '1')
                            OR (tokens.cap='" . TokenMintCapType::SINGLE_MINT->name . "' AND tokens.supply = '1')
                        )
                    ")
                    ->exists();
                if ($exists) {
                    return false;
                }
            }
            $ranges = collect($value)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            foreach ($ranges as $range) {
                [$from, $to] = $this->integerRange($range);
                $exists = BeamClaim::whereBetween('beam_claims.token_chain_id', [(int) $from, (int) $to])
                    ->leftJoin(
                        'tokens',
                        fn ($join) => $join->on('tokens.token_chain_id', '=', 'beam_claims.token_chain_id')
                            ->whereColumn('tokens.collection_id', 'beam_claims.collection_id')
                    )
                    ->join(
                        'collections',
                        fn ($join) => $join->on('collections.id', '=', 'beam_claims.collection_id')
                            ->where('collections.collection_chain_id', $this->collectionId)
                    )
                    ->whereRaw("
                        (tokens.is_currency is false OR tokens.is_currency is NULL)
                        AND (
                            collections.max_token_supply = '1'
                            OR (collections.force_single_mint is true AND tokens.supply = '1')
                            OR (tokens.cap='" . TokenMintCapType::SUPPLY->name . "' AND tokens.cap_supply = '1')
                            OR (tokens.cap='" . TokenMintCapType::SINGLE_MINT->name . "' AND tokens.supply = '1')
                        )
                    ")
                    ->exists();

                if ($exists) {
                    return false;
                }
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
        return __('enjin-platform-beam::validation.tokens_doesnt_exist_in_beam');
    }
}
