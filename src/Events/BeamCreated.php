<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class BeamCreated extends PlatformBroadcastEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(Model $beam)
    {
        parent::__construct();
        $this->broadcastData = Arr::only(
            $beam->withoutRelations()->toArray(),
            ['code', 'collection_chain_id']
        );
        $this->broadcastData['beamCode'] = $beam->code;

        $this->broadcastChannels = [
            new Channel("collection;{$this->broadcastData['collection_chain_id']}"),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
