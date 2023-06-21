<?php

namespace Enjin\Platform\Beam\Enums;

use Enjin\Platform\Traits\EnumExtensions;

enum BeamRoute: string
{
    use EnumExtensions;

    case CLAIM = 'claim/{code}';
}
