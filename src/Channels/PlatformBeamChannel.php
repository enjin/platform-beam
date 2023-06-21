<?php

namespace Enjin\Platform\Beam\Channels;

use Enjin\Platform\Channels\PlatformAppChannel;

class PlatformBeamChannel extends PlatformAppChannel
{
    /**
     * Creates new channel instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->name = 'beam;' . $this->name;
    }
}
