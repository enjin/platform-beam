<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\TokenAccount;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class MaxTokenSupply implements DataAwareRule, ValidationRule
{
    use HasDataAwareRule;
    use IntegerRange;

    /**
     * The max token supply limit.
     *
     * @var int
     */
    protected $limit;

    /**
     * The error messages.
     */
    protected string $maxTokenSupplyMessage = 'enjin-platform-beam::validation.max_token_supply';
    protected string $maxTokenBalanceMessage = 'enjin-platform::validation.max_token_balance';

    /**
     * Create instance of rule.
     */
    public function __construct(protected ?string $collectionId)
    {
    }

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
        /**
         * The total circulating supply of tokens must not exceed the collection's maximum token supply.
         * For example, if the maximum token count is 10 and the maximum token supply is 10,
         * the total circulating supply must not exceed 100.
         */
        if ($this->collectionId
            && ($collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]))
            && !is_null($this->limit = $collection->max_token_supply)
        ) {
            if ((Arr::get($this->data, str_replace('tokenQuantityPerClaim', 'type', $attribute)) == BeamType::MINT_ON_DEMAND->name
                    && !$collection->max_token_supply >= $value)
                || $this->limit == 0
            ) {
                $fail($this->maxTokenSupplyMessage)->translate(['limit' => $this->limit]);

                return;
            }

            if ($collection->max_token_count == 0) {
                $fail('enjin-platform-beam::validation.max_token_count')->translate(['limit' => $this->limit]);

                return;
            }

            $this->limit = $collection->max_token_supply * ($collection->max_token_count ?? 1);

            $balanceCount = TokenAccount::where('token_accounts.collection_id', $collection->id)->sum('balance');
            $claimCount = BeamClaim::where('type', BeamType::MINT_ON_DEMAND->name)
                ->whereHas('beam', fn ($query) => $query->where('collection_chain_id', $this->collectionId)->where('end', '>', now()))
                ->claimable()
                ->sum('quantity');

            $tokenCount = 0;
            $tokenCount = collect($this->data['tokens'])
                ->reduce(function ($carry, $token) {
                    if (Arr::get($token, 'tokenIds')) {
                        return collect($token['tokenIds'])->reduce(function ($val, $tokenId) use ($token) {
                            $range = $this->integerRange($tokenId);
                            $claimQuantity = Arr::get($token, 'claimQuantity', 1);
                            $quantityPerClaim = Arr::get($token, 'tokenQuantityPerClaim', 1);

                            return $val + (
                                $range === false
                                ? $claimQuantity * $quantityPerClaim
                                : (($range[1] - $range[0]) + 1) * $claimQuantity * $quantityPerClaim
                            );
                        }, $carry);
                    }

                    if (Arr::get($token, 'tokenIdDataUpload')) {
                        $total = 0;
                        $handle = fopen($token['tokenIdDataUpload']->getPathname(), 'r');
                        while (($line = fgets($handle)) !== false) {
                            $range = $this->integerRange(trim($line));
                            $claimQuantity = Arr::get($token, 'claimQuantity', 1);
                            $quantityPerClaim = Arr::get($token, 'tokenQuantityPerClaim', 1);
                            $total += (
                                $range === false
                                ? $claimQuantity * $quantityPerClaim
                                : (($range[1] - $range[0]) + 1) * $claimQuantity * $quantityPerClaim
                            );
                        }
                        fclose($handle);

                        return $total;
                    }
                }, $tokenCount);

            if ($this->limit < $balanceCount + $claimCount + $tokenCount) {
                $fail($this->maxTokenSupplyMessage)->translate(['limit' => $this->limit]);
            }
        }
    }
}
