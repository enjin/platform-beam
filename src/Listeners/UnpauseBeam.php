<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Enums\BeamFlag;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UnpauseBeam implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        Beam::where('collection_chain_id', $event->broadcastData['collectionId'])
            ->get()
            ->each(function ($beam) {
                $beam->update(['flags_mask' => BeamService::getFlagsValue(
                    collect(array_merge($beam->flags ?? [], ['PAUSED']))
                        ->unique()
                        ->map(fn ($flag) => ['flag' => $flag, 'enabled' => $flag != BeamFlag::PAUSED->name])->all()
                )]);
                Log::info("Pausing beam {$beam->code} cause the collection {$beam->collection_chain_id} was paused.");
            });
    }
}
