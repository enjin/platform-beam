<?php

namespace Enjin\Platform\Beam\Tests\Unit;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Jobs\ClaimBeam;
use Enjin\Platform\Beam\Listeners\ExpireBeam;
use Enjin\Platform\Beam\Listeners\PauseBeam;
use Enjin\Platform\Beam\Listeners\RemoveClaimToken;
use Enjin\Platform\Beam\Listeners\UnpauseBeam;
use Enjin\Platform\Beam\Listeners\UpdateClaimCollectionIds;
use Enjin\Platform\Beam\Listeners\UpdateClaimStatus;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BatchService;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Beam\Tests\Feature\Traits\SeedBeamData;
use Enjin\Platform\Events\Global\TransactionUpdated;
use Enjin\Platform\Events\Substrate\Commands\PlatformSynced;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionDestroyed;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionFrozen;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionThawed;
use Enjin\Platform\Events\Substrate\MultiTokens\TokenDestroyed;
use Enjin\Platform\Models\Laravel\Transaction;
use Enjin\Platform\Services\Database\WalletService;
use Enjin\Platform\Services\Processor\Substrate\Codec\Polkadart\Events\MultiTokens\CollectionDestroyed as CollectionDestroyedPolkadart;
use Enjin\Platform\Services\Processor\Substrate\Codec\Polkadart\Events\MultiTokens\Frozen as CollectionFrozenPolkadart;
use Enjin\Platform\Services\Processor\Substrate\Codec\Polkadart\Events\MultiTokens\Thawed as CollectionThawedPolkadart;
use Enjin\Platform\Services\Processor\Substrate\Codec\Polkadart\Events\MultiTokens\TokenDestroyed as TokenDestroyedPolkadart;
use Illuminate\Support\Facades\Event;

class EventTest extends TestCaseGraphQL
{
    use SeedBeamData;

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBeam();
    }

    public function test_it_can_listens_to_events(): void
    {
        Event::fake();

        $collectionId = $this->collection->collection_chain_id;

        $collectionDestroyed = CollectionDestroyedPolkadart::fromChain($this->mockPolkadartEvent('MultiTokens', 'CollectionDestroyed', [
            'T::CollectionId' => $collectionId,
            'T::AccountId' => 'd43593c715fdd31c61141abd04a99fd6822c8558854ccde39a5684e7a56da27d',
        ]));

        event($event = new CollectionDestroyed($collectionDestroyed));
        Event::assertListening(CollectionDestroyed::class, ExpireBeam::class);
        resolve(ExpireBeam::class)->handle($event);

        $collectionFrozen = CollectionFrozenPolkadart::fromChain($this->mockPolkadartEvent('MultiTokens', 'Frozen', [
            'FreezeOf<T>.collection_id' => $collectionId,
            'FreezeOf<T>.freeze_type' => 'Collection',
        ]));

        event($event = new CollectionFrozen($collectionFrozen));
        Event::assertListening(CollectionFrozen::class, PauseBeam::class);
        resolve(PauseBeam::class)->handle($event);

        $collectionThawed = CollectionThawedPolkadart::fromChain($this->mockPolkadartEvent('MultiTokens', 'Thawed', [
            'FreezeOf<T>.collection_id' => $collectionId,
            'FreezeOf<T>.freeze_type' => 'Collection',
        ]));

        event($event = new CollectionThawed($collectionThawed));
        Event::assertListening(CollectionThawed::class, UnpauseBeam::class);
        resolve(UnpauseBeam::class)->handle($event);

        event($event = new PlatformSynced());
        Event::assertListening(PlatformSynced::class, UpdateClaimCollectionIds::class);
        resolve(UpdateClaimCollectionIds::class)->handle($event);

        $transaction = Transaction::factory()->create();
        event($event = new TransactionUpdated(event: [], transaction: $transaction));
        Event::assertListening(TransactionUpdated::class, UpdateClaimStatus::class);
        resolve(UpdateClaimStatus::class)->handle($event);

        $tokenDestroyed = TokenDestroyedPolkadart::fromChain($this->mockPolkadartEvent('MultiTokens', 'TokenDestroyed', [
            'T::CollectionId' => $collectionId,
            'T::TokenId' => $this->token->token_chain_id,
            'T::AccountId' => $this->wallet->public_key,
        ]));

        event($event = new TokenDestroyed($tokenDestroyed));
        $this->collection->update([
            'max_token_supply' => 100,
            'force_collapsing_supply' => false,
        ]);
        BeamClaim::where('beam_id', $this->beam->id)->first()->update([
            'collection_id' => $this->collection->id,
            'token_chain_id' => $this->token->token_chain_id,
        ]);
        Event::assertListening(TokenDestroyed::class, RemoveClaimToken::class);
        resolve(RemoveClaimToken::class)->handle($event);
    }

    public function test_it_can_claim_job(): void
    {
        $job = new ClaimBeam([
            'beam' => $this->beam->toArray(),
            'wallet_public_key' => $this->wallet->public_key,
            'claimed_at' => now(),
            'state' => ClaimStatus::PENDING->name,
            'code' => '',
        ]);
        $job->handle(resolve(BatchService::class), resolve(WalletService::class));
        $this->assertLessThan($this->claims->count(), BeamClaim::where('beam_id', $this->beam->id)->claimable()->count());
    }

    protected function mockPolkadartEvent(string $module, string $name, array $data): array
    {
        return [
            'phase' => [
                'ApplyExtrinsic' => 1,
            ],
            'event' => [
                $module => [
                    $name => $data,
                ],
            ],
        ];
    }
}
