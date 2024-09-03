<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Enjin\Platform\Models\Collection;
use Enjin\Platform\Models\Token;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class RemoveClaimToken implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        $collection = Cache::remember(
            'collection:' . $event->broadcastData['collectionId'],
            30,
            fn () => Collection::where('collection_chain_id', $event->broadcastData['collectionId'])->first(['id'])
        );

        if (!$collection) {
            return;
        }

        if (Token::where('collection_id', $collection->id)
            ->where('token_chain_id', $event->broadcastData['tokenId'])
            ->first()
            ?->nonFungible
        ) {
            $beamsToDecrement = BeamClaim::where('token_chain_id', $event->broadcastData['tokenId'])
                ->where('collection_id', $collection->id)
                ->selectRaw('beam_id, count(*) total')
                ->claimable()
                ->groupBy('beam_id')
                ->pluck('total', 'beam_id');

            BeamClaim::where('token_chain_id', $event->broadcastData['tokenId'])
                ->where('collection_id', $collection->id)
                ->claimable()
                ->delete();

            Beam::whereIn('id', $beamsToDecrement->keys())
                ->get(['id', 'code'])
                ->each(fn ($beam) => Cache::decrement(BeamService::key($beam->code), $beamsToDecrement[$beam->id]));
        }


    }
}
