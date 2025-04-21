<?php

namespace Enjin\Platform\Beam\Enums;

use Enjin\Platform\Traits\EnumExtensions;

enum BeamFlag: int
{
    use EnumExtensions;

    case PAUSED = 0;
    case SINGLE_USE = 1;
    case PRUNABLE = 2;
}
