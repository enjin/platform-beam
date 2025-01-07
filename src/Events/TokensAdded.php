<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Beam\Traits\HasCustomQueue;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;

class TokensAdded extends PlatformBroadcastEvent
{
    use HasCustomQueue;

    /**
     * Create a new event instance.
     */
    public function __construct(mixed $event)
    {
        parent::__construct();

        $this->broadcastData = $event;

        $this->broadcastChannels = [
            new Channel("beam;{$event['code']}"),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
