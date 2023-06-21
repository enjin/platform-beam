<?php

namespace Enjin\Platform\Beam\Enums;

use Enjin\Platform\Traits\EnumExtensions;

enum BeamType: string
{
    use EnumExtensions;

    case TRANSFER_TOKEN = 'TransferToken';
    case MINT_ON_DEMAND = 'MintOnDemand';
}
