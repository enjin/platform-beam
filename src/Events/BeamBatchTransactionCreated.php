<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;

class BeamBatchTransactionCreated extends PlatformBroadcastEvent
{
    /**
     * Creates a new event instance.
     */
    public function __construct(string $beamCode, string $collectionId, int $transactionId)
    {
        parent::__construct();

        $this->broadcastData = [
            'beamId' => $beamCode,
            'collectionId' => $collectionId,
            'transactionId' => $transactionId,
        ];

        $this->broadcastChannels = [
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
