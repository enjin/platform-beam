<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Enums\BeamType;
use Enjin\Platform\Beam\Models\Beam;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Beam\Services\BeamService;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PauseBeam implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        foreach (Beam::where('collection_chain_id', $event->broadcastData['collectionId'])->get() as $beam) {
            $hasTransfers = BeamClaim::claimable()
                ->where('type', BeamType::TRANSFER_TOKEN->name)
                ->where('beam_id', $beam->id)
                ->exists();
            $hasMints = BeamClaim::claimable()
                ->where('type', BeamType::MINT_ON_DEMAND->name)
                ->where('beam_id', $beam->id)
                ->exists();
            if (($hasMints && !$hasTransfers) || ($hasMints && $hasTransfers)) {
                continue;
            }

            $beam->update(['flags_mask' => BeamService::getFlagsValue(
                collect(array_merge($beam->flags ?? [], ['PAUSED']))
                    ->unique()
                    ->map(fn ($flag) => ['flag' => $flag, 'enabled' => true])->all()
            )]);

            Log::info("Pausing beam {$beam->code} cause the collection {$beam->collection_chain_id} was paused.");
        }
    }
}
