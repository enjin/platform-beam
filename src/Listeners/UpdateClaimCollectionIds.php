<?php

namespace Enjin\Platform\Beam\Listeners;

use Enjin\Platform\Beam\Services\BeamClaimService;
use Enjin\Platform\Events\Substrate\Commands\PlatformSynced;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateClaimCollectionIds implements ShouldQueue
{
    public function __construct(private readonly BeamClaimService $beamClaimService) {}

    /**
     * Handle the event.
     */
    public function handle(PlatformSynced $event): void
    {
        $this->beamClaimService->syncCollectionIds();
    }

    public function viaQueue(): string
    {
        return config('enjin-platform-beam.queue');
    }
}
