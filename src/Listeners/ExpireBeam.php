<?php

namespace Enjin\Platform\Beam\Listeners;

use Carbon\Carbon;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ExpireBeam implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        Beam::where('collection_chain_id', $event->broadcastData['collectionId'])
            ->get()
            ->each(function ($beam) {
                $beam->end = Carbon::now();
                $beam->save();
                Log::info("Expiring beam {$beam->code} because the collection {$beam->collection_chain_id} was deleted.");
            });
    }

    public function viaQueue(): string
    {
        return config('enjin-platform-beam.queue');
    }
}
