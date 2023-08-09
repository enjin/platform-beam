<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Enums\ClaimStatus;
use Enjin\Platform\Beam\Events\BeamClaimsComplete;
use Enjin\Platform\Beam\Events\BeamClaimsFailed;
use Enjin\Platform\Beam\Models\BeamBatch;
use Enjin\Platform\Beam\Models\BeamClaim;
use Enjin\Platform\Enums\Global\TransactionState;
use Enjin\Platform\Enums\Substrate\SystemEventType;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateClaimStatus implements ShouldQueue
{
    /**
     * Create new event listener instance.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(PlatformBroadcastEvent $event): void
    {
        $states = [TransactionState::ABANDONED->name, TransactionState::FINALIZED->name];
        if (in_array($event->broadcastData['state'], $states)) {
            $claims = BeamClaim::whereHas('batch', fn ($query) => $query->where('transaction_id', $event->broadcastData['id']))
                ->select('*', 'code as identifierCode')
                ->with('beam')
                ->get();

            if (!$claims->isEmpty()) {
                $state = TransactionState::FINALIZED->name == $event->broadcastData['state']
                        && $event->broadcastData['result'] == SystemEventType::EXTRINSIC_SUCCESS->name
                                ? ClaimStatus::COMPLETED->name
                                : ClaimStatus::FAILED->name;
                BeamClaim::whereIn('id', $claims->pluck('id'))->update(['state' => $state]);
                BeamBatch::where('transaction_id', $event->broadcastData['id'])->update(['processed_at' => now()]);
                foreach ($claims as $claim) {
                    $claim->state = $state;
                    event(
                        $state == ClaimStatus::COMPLETED->name
                            ? new BeamClaimsComplete($claim->toArray())
                            : new BeamClaimsFailed($claim->toArray())
                    );
                }
            }
        }
    }
}
