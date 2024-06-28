<?php

namespace Enjin\Platform\Beam;

use Enjin\Platform\Beam\Enums\BeamRoute;
use Enjin\Platform\Package as CorePackage;

class Package extends CorePackage
{
    /**
     * Get any routes that have been set up for this package.
     */
    public static function getPackageRoutes(): array
    {
        return BeamRoute::caseValuesAsArray();
    }
}
