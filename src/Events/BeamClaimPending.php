<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Arr;

class BeamClaimPending extends PlatformBroadcastEvent
{
    /**
     * Creates a new event instance.
     */
    public function __construct(array $claim)
    {
        parent::__construct();
        $this->broadcastData = Arr::except($claim, 'code');
        $this->broadcastChannels = [
            new Channel('collection;' . $claim['beam']['collection_chain_id']),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
