<?php

namespace Enjin\Platform\Beam\Listeners;

use Carbon\Carbon;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Traits\HasCustomQueue;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ExpireBeam implements ShouldQueue
{
    use HasCustomQueue;

    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        Beam::where('collection_chain_id', $event->broadcastData['collectionId'])
            ->get()
            ->each(function ($beam): void {
                $beam->end = Carbon::now();
                $beam->save();
                Log::info("Expiring beam {$beam->code} because the collection {$beam->collection_chain_id} was deleted.");
            });
    }
}
