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
use Enjin\Platform\Services\Database\WalletService;
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

        event($event = new CollectionDestroyed($this->collection));
        Event::assertListening(CollectionDestroyed::class, ExpireBeam::class);
        resolve(ExpireBeam::class)->handle($event);

        event($event = new CollectionFrozen($this->collection));
        Event::assertListening(CollectionFrozen::class, PauseBeam::class);
        resolve(PauseBeam::class)->handle($event);

        event($event = new CollectionThawed($this->collection));
        Event::assertListening(CollectionThawed::class, UnpauseBeam::class);
        resolve(UnpauseBeam::class)->handle($event);

        event($event = new PlatformSynced($this->collection));
        Event::assertListening(PlatformSynced::class, UpdateClaimCollectionIds::class);
        resolve(UpdateClaimCollectionIds::class)->handle($event);

        event($event = new TransactionUpdated($this->collection));
        Event::assertListening(TransactionUpdated::class, UpdateClaimStatus::class);
        resolve(UpdateClaimStatus::class)->handle($event);

        event($event = new TokenDestroyed($this->token, $this->wallet));
        $this->collection->update([
            'max_token_supply' => 100,
            'force_single_mint' => false,
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
}
