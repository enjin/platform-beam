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

        $this->broadcastData = array_merge(
            Arr::only($claim['beam'], ['code', 'collection_chain_id']),
            Arr::only($claim, ['wallet_public_key', 'claimed_at', 'state'])
        );
        $this->broadcastData['identifierCode'] = Arr::get($claim, 'identifierCode');
        $this->broadcastData['beamCode'] = $claim['beam']['code'];

        $this->broadcastChannels = [
            new Channel('collection;' . $claim['beam']['collection_chain_id']),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
