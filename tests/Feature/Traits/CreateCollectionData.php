<?php

namespace Enjin\Platform\Beam\Tests\Feature\Traits;

use Enjin\Platform\Enums\Substrate\TokenMintCapType;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\Token;
use Enjin\Platform\Models\TokenAccount;
use Enjin\Platform\Models\Wallet;
use Enjin\Platform\Support\Account;
use Illuminate\Database\Eloquent\Model;

trait CreateCollectionData
{
    /**
     * The wallet account.
     */
    protected Model $wallet;

    /**
     * The collection.
     */
    protected Model $collection;

    /**
     * The token.
     */
    protected Model $token;

    /**
     * Create collection data.
     */
    public function prepareCollectionData(?string $publicKey = null): void
    {
        $this->wallet = Wallet::firstOrCreate(
            ['public_key' => $publicKey ?: Account::daemonPublicKey()],
            [
                'external_id' => fake()->unique()->uuid(),
                'managed' => fake()->boolean(),
                'verification_id' => fake()->unique()->uuid(),
                'network' => 'developer',
                'linking_code' => null,
            ]
        );

        $this->collection = Collection::create([
            'collection_chain_id' => (string) fake()->unique()->numberBetween(2000),
            'owner_wallet_id' => $this->wallet->id,
            'max_token_count' => fake()->numberBetween(1),
            'max_token_supply' => (string) fake()->numberBetween(1),
            'force_collapsing_supply' => fake()->boolean(),
            'is_frozen' => false,
            'token_count' => '0',
            'attribute_count' => '0',
            'total_deposit' => '0',
            'network' => 'developer',
        ]);

        $this->token = Token::create([
            'collection_id' => $this->collection->id,
            'token_chain_id' => (string) fake()->unique()->numberBetween(2000),
            'supply' => (string) $supply = fake()->numberBetween(1),
            'cap' => TokenMintCapType::COLLAPSING_SUPPLY->name,
            'cap_supply' => null,
            'is_frozen' => false,
            'unit_price' => (string) $unitPrice = fake()->numberBetween(1 / $supply * 10 ** 17),
            'mint_deposit' => (string) ($unitPrice * $supply),
            'minimum_balance' => '1',
            'attribute_count' => '0',
        ]);

        TokenAccount::create([
            'collection_id' => $this->collection->id,
            'token_id' => $this->token->id,
            'wallet_id' => $this->wallet->id,
            'balance' => 1,
        ]);
    }
}
