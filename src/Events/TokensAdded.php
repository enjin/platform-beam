<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Model;

class TokensAdded extends PlatformBroadcastEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct(mixed $event, ?Model $transaction, ?array $extra, array $data)
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
