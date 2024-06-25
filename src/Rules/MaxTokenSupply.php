<?php

namespace Enjin\Platform\Beam\Rules;

use Closure;
use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Rules\Traits\IntegerRange;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\TokenAccount;
use Enjin\Platform\Models\Wallet;
use Enjin\Platform\Rules\Traits\HasDataAwareRule;
use Enjin\Platform\Support\Account;
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
    public function __construct(protected ?string $collectionId) {}

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
        if ($this->collectionId
            && ($collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]))
            && !is_null($this->limit = $collection->max_token_supply)
        ) {
            if (Arr::get($this->data, str_replace('tokenQuantityPerClaim', 'type', $attribute)) == BeamType::MINT_ON_DEMAND->name) {
                if (!$collection->max_token_supply >= $value) {
                    $fail($this->maxTokenSupplyMessage)
                        ->translate([
                            'limit' => $this->limit,
                        ]);

                    return;
                }
            }

            $tokenIds = Arr::get($this->data, str_replace('tokenQuantityPerClaim', 'tokenIds', $attribute));
            $integers = collect($tokenIds)->filter(fn ($val) => false === $this->integerRange($val))->all();
            if ($integers) {
                $wallet = Wallet::firstWhere(['public_key' => Account::daemonPublicKey()]);
                $collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]);
                if (!$wallet || !$collection) {
                    $fail($this->maxTokenSupplyMessage)
                        ->translate([
                            'limit' => $this->limit,
                        ]);

                    return;
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
                        $fail($this->maxTokenBalanceMessage)->translate();

                        return;
                    }
                }
            }

            $ranges = collect($tokenIds)->filter(fn ($val) => false !== $this->integerRange($val))->all();
            if ($ranges) {
                $wallet = Wallet::firstWhere(['public_key' => Account::daemonPublicKey()]);
                $collection = Collection::firstWhere(['collection_chain_id' => $this->collectionId]);
                if (!$wallet || !$collection) {
                    $fail($this->maxTokenSupplyMessage)
                        ->translate([
                            'limit' => $this->limit,
                        ]);

                    return;
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
                            $fail($this->maxTokenBalanceMessage)->translate();
                        }
                    }
                }
            }
        }
    }
}
