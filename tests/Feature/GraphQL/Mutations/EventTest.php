<?php

declare(strict_types=1);

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL\Mutations;

use Enjin\Platform\Beam\Listeners\ExpireBeam;
use Enjin\Platform\Beam\Listeners\PauseBeam;
use Enjin\Platform\Beam\Listeners\RemoveClaimToken;
use Enjin\Platform\Beam\Listeners\UnpauseBeam;
use Enjin\Platform\Beam\Listeners\UpdateClaimCollectionIds;
use Enjin\Platform\Beam\Listeners\UpdateClaimStatus;
use Enjin\Platform\Beam\Tests\Feature\GraphQL\TestCaseGraphQL;
use Enjin\Platform\Events\Global\TransactionUpdated;
use Enjin\Platform\Events\Substrate\Commands\PlatformSynced;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionDestroyed;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionFrozen;
use Enjin\Platform\Events\Substrate\MultiTokens\CollectionThawed;
use Enjin\Platform\Events\Substrate\MultiTokens\TokenDestroyed;
use Illuminate\Support\Facades\Event;

class EventTest extends TestCaseGraphQL
{
    public function test_it_can_listens_to_events(): void
    {
        Event::fake();

        event(new CollectionDestroyed($this->collection));
        Event::assertListening(CollectionDestroyed::class, ExpireBeam::class);
        event(new CollectionFrozen($this->collection));
        Event::assertListening(CollectionFrozen::class, PauseBeam::class);
        event(new TokenDestroyed($this->token, $this->wallet));
        Event::assertListening(TokenDestroyed::class, RemoveClaimToken::class);
        event(new CollectionThawed($this->collection));
        Event::assertListening(CollectionThawed::class, UnpauseBeam::class);
        event(new PlatformSynced($this->collection));
        Event::assertListening(PlatformSynced::class, UpdateClaimCollectionIds::class);
        event(new TransactionUpdated($this->collection));
        Event::assertListening(TransactionUpdated::class, UpdateClaimStatus::class);
    }
}
