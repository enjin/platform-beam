<?php

namespace Enjin\Platform\Beam\Rules;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\TokenAccount;
use Enjin\Platform\Models\Wallet;
use Enjin\Platform\Support\Account;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class MaxTokenSupply implements DataAwareRule, Rule
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
     * The error message.
     */
    protected string $error = 'enjin-platform-beam::validation.max_token_supply';

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
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($this->collectionId
            && ($collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]))
            && !is_null($this->limit = $collection->max_token_supply)
        ) {
            if (Arr::get($this->data, str_replace('tokenQuantityPerClaim', 'type', $attribute)) == BeamType::MINT_ON_DEMAND->name) {
                return $collection->max_token_supply >= $value;
            }

            $tokenIds = Arr::get($this->data, str_replace('tokenQuantityPerClaim', 'tokenIds', $attribute));
            $integers = collect($tokenIds)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                $wallet = Wallet::firstWhere(['public_key' => Account::daemonPublicKey()]);
                $collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]);
                if (!$wallet || !$collection) {
                    return false;
                }
                $accounts = TokenAccount::join('tokens', 'tokens.id', '=', 'token_accounts.token_id')
                    ->where('token_accounts.wallet_id', $wallet->id)
                    ->where('token_accounts.collection_id', $collection->id)
                    ->whereIn('tokens.token_chain_id', $integers)
                    ->selectRaw('tokens.token_chain_id, sum(token_accounts.balance) as balance')
                    ->groupBy('tokens.token_chain_id')
                    ->get();

                $claims = BeamClaim::whereHas(
                    'beam',
                    fn ($query) => $query->where('collection_chain_id', $this->collectionId)->where('end', '>', now())
                )->where('type', BeamType::TRANSFER_TOKEN->name)
                    ->whereIn('token_chain_id', $integers)
                    ->whereNull('wallet_public_key')
                    ->selectRaw('token_chain_id, sum(quantity) as quantity')
                    ->groupBy('token_chain_id')
                    ->pluck('quantity', 'token_chain_id');
                foreach ($accounts as $account) {
                    if ((int) $account->balance < $value + Arr::get($claims, $account->token_chain_id, 0)) {
                        $this->error = 'enjin-platform::validation.max_token_balance';

                        return false;
                    }
                }
            }

            $ranges = collect($tokenIds)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            if ($ranges) {
                $wallet = Wallet::firstWhere(['public_key' => Account::daemonPublicKey()]);
                $collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]);
                if (!$wallet || !$collection) {
                    return false;
                }
                foreach ($ranges as $range) {
                    [$from, $to] = $this->integerRange($range);
                    $accounts = TokenAccount::join('tokens', 'tokens.id', '=', 'token_accounts.token_id')
                        ->where('token_accounts.wallet_id', $wallet->id)
                        ->where('token_accounts.collection_id', $collection->id)
                        ->whereBetween('tokens.token_chain_id', [(int) $from, (int) $to])
                        ->selectRaw('tokens.token_chain_id, sum(token_accounts.balance) as balance')
                        ->groupBy('tokens.token_chain_id')
                        ->get();

                    $claims = BeamClaim::whereHas(
                        'beam',
                        fn ($query) => $query->where('collection_chain_id', $this->collectionId)->where('end', '>', now())
                    )->where('type', BeamType::TRANSFER_TOKEN->name)
                        ->whereBetween('token_chain_id', [(int) $from, (int) $to])
                        ->whereNull('wallet_public_key')
                        ->selectRaw('token_chain_id, sum(quantity) as quantity')
                        ->groupBy('token_chain_id')
                        ->pluck('quantity', 'token_chain_id');
                    foreach ($accounts as $account) {
                        if ((int) $account->balance < $value + Arr::get($claims, $account->token_chain_id, 0)) {
                            $this->error = 'enjin-platform::validation.max_token_balance';

                            return false;
                        }
                    }
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
        return __($this->error, ['limit' => $this->limit]);
    }
}
