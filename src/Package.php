<?php

namespace Enjin\Platform\Beam;

use Enjin\Platform\Beam\Enums\BeamRoute;

class Package
{
    /**
     * Get the composer autoloader for auto-bootstrapping services.
     */
    public static function getAutoloader(): string
    {
        // TODO: It doesn't work on a real laravel env if using the below
        // $loader = require realpath(__DIR__ . 'vendor/autoload.php');
        // TODO: If using the below it doesn't work on testbench, neither composer will install
        // $loader = require app()->basePath('../../../autoload.php')
        return app()->runningUnitTests() ? require app()->basePath('../../../autoload.php') : require app()->basePath('vendor/autoload.php');
    }

    /**
     * Get any routes that have been set up for this package.
     */
    public static function getPackageRoutes(): array
    {
        return BeamRoute::caseValuesAsArray();
    }
}
