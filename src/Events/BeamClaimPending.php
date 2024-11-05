<?php

namespace Enjin\Platform\Beam\Events;

use Enjin\Platform\Beam\Channels\PlatformBeamChannel;
use Enjin\Platform\Beam\Traits\HasCustomQueue;
use Enjin\Platform\Channels\PlatformAppChannel;
use Enjin\Platform\Events\PlatformBroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Arr;

class BeamClaimPending extends PlatformBroadcastEvent
{
    use HasCustomQueue;

    /**
     * Creates a new event instance.
     */
    public function __construct(array $claim)
    {
        parent::__construct();

        $this->broadcastData = array_merge(
            //idempotency_key needs to be present both since for some cases it's not set in $claim['beam']
            Arr::only($claim['beam'], ['code', 'collection_chain_id', 'idempotency_key']),
            Arr::only($claim, ['wallet_public_key', 'claimed_at', 'state', 'token_chain_id', 'idempotency_key']),
            [
                'identifierCode' => Arr::get($claim, 'identifierCode'),
                'beamCode' => Arr::get($claim, 'beam.code'),
                'transactionHash' => Arr::get($claim, 'transactionHash'),
                'transactionId' => Arr::get($claim, 'transactionId'),
            ]
        );

        $this->broadcastChannels = [
            new Channel('collection;' . $claim['beam']['collection_chain_id']),
            new PlatformAppChannel(),
            new PlatformBeamChannel(),
        ];
    }
}
