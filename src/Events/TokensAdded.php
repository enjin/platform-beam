<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;

class TokensAdded extends PlatformBroadcastEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        parent::__construct();
        $this->broadcastData = $data;
        $this->broadcastChannels = [
            new Channel("beam;{$this->broadcastData['code']}"),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
