<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Database\Eloquent\Model;

class BeamBatchTransactionCreated extends PlatformBroadcastEvent
{
    /**
     * Creates a new event instance.
     */
    public function __construct(mixed $event, ?Model $transaction, ?array $extra)
    {
        parent::__construct();

        $this->broadcastData = [
            'beamId' => $event['beam_id'],
            'collectionId' => $event['collection_id'],
            'transactionId' => $transaction?->id,
        ];

        $this->broadcastChannels = [
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
