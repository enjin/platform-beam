<?php

namespace Enjin\Platform\Beam\Enums;

use Enjin\Platform\Traits\EnumExtensions;

enum ClaimStatus: string
{
    use EnumExtensions;

    case PENDING = 'Pending';
    case IN_PROGRESS = 'InProgress';
    case COMPLETED = 'Completed';
    case FAILED = 'Failed';
}
