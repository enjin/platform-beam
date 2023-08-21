<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Arr;

class CreateBeamClaimsCompleted extends PlatformBroadcastEvent
{
    /**
     * Creates a new event instance.
     */
    public function __construct(array $data)
    {
        parent::__construct();

        $this->broadcastData = $data;

        $this->broadcastChannels = [
            new Channel('beam;' . Arr::get($data, 'code')),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
