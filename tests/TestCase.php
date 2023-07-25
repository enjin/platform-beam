<?php

namespace Enjin\Platform\Beam\Tests;

use Enjin\Platform\Beam\BeamServiceProvider;
use Enjin\Platform\CoreServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fake events flag.
     */
    protected bool $fakeEvents = true;

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app)
    {
        return [
            CoreServiceProvider::class,
            BeamServiceProvider::class,
        ];
    }

    /**
     * Define environment.
     *
     * @param mixed $app
     *
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Make sure, our .env file is loaded for local tests
        $app->useEnvironmentPath(__DIR__ . '/..');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        $app['config']->set('database.default', env('DB_DRIVER', 'mysql'));

        // MySQL config
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1:3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'password'),
            'database' => env('DB_DATABASE', 'platform'),
            'prefix' => '',
        ]);

        if ($this->fakeEvents) {
            Event::fake();
        }
    }

    /**
     * Uses null daemon account.
     */
    protected function usesNullDaemonAccount($app)
    {
        $app->config->set('enjin-platform.chains.daemon-account', '0x0000000000000000000000000000000000000000000000000000000000000000');
    }

    /**
     * Uses enjin network.
     */
    protected function usesEnjinNetwork($app)
    {
        $app->config->set('enjin-platform.chains.network', 'enjin');
    }

    /**
     * Uses developer network.
     */
    protected function usesDeveloperNetwork($app)
    {
        $app->config->set('enjin-platform.chains.network', 'developer');
    }
}
