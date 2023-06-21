<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class RemoveClaimToken implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        BeamClaim::where('token_chain_id', $event->broadcastData['tokenId'])
            ->whereHas(
                'collection',
                fn ($query) => $query->where('collection_chain_id', $event->broadcastData['collectionId'])
            )
            ->claimable()
            ->with('beam')
            ->get()
            ->each(function ($claim) {
                if ($claim->token?->nonFungible) {
                    $claim->delete();
                    if ($code = $claim?->beam?->code) {
                        Cache::decrement(BeamService::key($code));
                    }
                }
            });
    }
}
