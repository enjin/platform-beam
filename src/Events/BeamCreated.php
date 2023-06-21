<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Model;

class BeamCreated extends PlatformBroadcastEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(Model $beam)
    {
        parent::__construct();
        $this->broadcastData = $beam->withoutRelations()->toArray();
        $this->broadcastChannels = [
            new Channel("collection;{$this->broadcastData['collection_chain_id']}"),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
