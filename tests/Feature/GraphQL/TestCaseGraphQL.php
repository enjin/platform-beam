<?php

namespace Enjin\Platform\Beam\Tests\Feature\GraphQL;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Enjin\Platform\Beam\BeamServiceProvider;
use Enjin\Platform\Beam\Tests\Feature\Traits\CreateCollectionData;
use Enjin\Platform\CoreServiceProvider;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PHPUnit\Framework\ExpectationFailedException;
use Rebing\GraphQL\Support\Facades\GraphQL;

class TestCaseGraphQL extends BaseTestCase
{
    use CreateCollectionData;
    use ArraySubsetAsserts;

    /**
     * The graphql queries.
     */
    protected static array $queries = [];

    /**
     * Initialize flag.
     */
    protected static bool $initialized = false;

    /**
     * Fake events flag.
     */
    protected bool $fakeEvents = true;

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            $this->artisan('migrate:fresh');
            $this->loadQueries();

            self::$initialized = true;
        }

        $this->prepareCollectionData();
    }

    /**
     * Call graphql endpoint.
     */
    public function graphql(string $query, array $arguments = [], ?bool $expectError = false): mixed
    {
        $result = GraphQL::queryAndReturnResult(self::$queries[$query], $arguments, ['schema' => 'beam']);
        $data = $result->toArray();

        $assertMessage = null;

        if (!$expectError && isset($data['errors'])) {
            $appendErrors = '';

            if (isset($data['errors'][0]['trace'])) {
                $appendErrors = "\n\n" . $this->formatSafeTrace($data['errors'][0]['trace']);
            }

            $assertMessage = "Probably unexpected error in GraphQL response:\n"
                . var_export($data, true)
                . $appendErrors;
        }
        unset($data['errors'][0]['trace']);

        if ($assertMessage) {
            throw new ExpectationFailedException($assertMessage);
        }

        if ('validation' === Arr::get($data, 'errors.0.message')) {
            $data['error'] = Arr::first($result->errors)?->getPrevious()->getValidatorMessages()->toArray();
        } elseif (null !== Arr::get($data, 'errors.0.message')) {
            $data['error'] = $data['errors'][0]['message'];
        }

        return $expectError ? $data : Arr::get($data['data'], $query);
    }

    /**
     * Helper to dispatch an HTTP GraphQL requests.
     */
    protected function httpGraphql(string $method, array $options = [], array $headers = []): mixed
    {
        $query = self::$queries[$method];
        $expectedHttpStatusCode = $options['httpStatusCode'] ?? 200;
        $expectErrors = $options['expectErrors'] ?? false;
        $variables = $options['variables'] ?? null;
        $schemaName = $options['schemaName'] ?? null;

        $payload = ['query' => $query];
        if ($variables) {
            $payload['variables'] = $variables;
        }

        $response = $this->json(
            'POST',
            '/graphql' . ($schemaName ? "/{$schemaName}" : ''),
            $payload,
            $headers
        );
        $result = $response->getData(true);

        $httpStatusCode = $response->getStatusCode();
        if ($expectedHttpStatusCode !== $httpStatusCode) {
            self::assertSame($expectedHttpStatusCode, $httpStatusCode, var_export($result, true) . "\n");
        }

        $assertMessage = null;
        if (!$expectErrors && isset($result['errors'])) {
            $appendErrors = '';
            if (isset($result['errors'][0]['trace'])) {
                $appendErrors = "\n\n" . $this->formatSafeTrace($result['errors'][0]['trace']);
            }

            $assertMessage = "Probably unexpected error in GraphQL response:\n"
                . var_export($result, true)
                . $appendErrors;
        }
        unset($result['errors'][0]['trace']);

        if ($assertMessage) {
            throw new ExpectationFailedException($assertMessage);
        }

        return Arr::get($result, "data.{$method}");
    }

    /**
     * Load queries from resource.
     */
    protected function loadQueries(): void
    {
        $files = scandir(__DIR__ . '/Resources');
        collect($files)
            ->filter(fn ($file) => str_ends_with($file, '.gql') || str_ends_with($file, '.graphql'))
            ->each(
                fn ($file) => self::$queries[str_replace(['.gql', '.graphql'], '', $file)] = file_get_contents(__DIR__ . '/Resources/' . $file)
            );
    }

    /**
     * Get package providers.
     *
     * @param mixed $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
            BeamServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param mixed $app
     */
    protected function getPackageAliases($app): array
    {
        return [];
    }

    /**
     * Define environment.
     *
     * @param mixed $app
     */
    protected function defineEnvironment($app): void
    {
        $app->useEnvironmentPath(__DIR__ . '/..');
        $app->useDatabasePath(__DIR__ . '/../../../database');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        $app['config']->set('database.default', env('DB_DRIVER', 'mysql'));
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'password'),
            'database' => env('DB_DATABASE', 'platform'),
            'port' => env('DB_PORT', '3306'),
            'prefix' => '',
        ]);

        $app['config']->set('app.debug', true);

        if ($this->fakeEvents) {
            Event::fake();
        }
    }

    /**
     * Converts the trace as generated from \GraphQL\Error\FormattedError::toSafeTrace
     * to a more human-readable string for a failed test.
     */
    private function formatSafeTrace(array $trace): string
    {
        return implode(
            "\n",
            array_map(static function (array $row, int $index): string {
                $line = "#{$index} ";
                $line .= $row['file'] ?? '';

                if (isset($row['line'])) {
                    $line .= "({$row['line']}) :";
                }

                if (isset($row['call'])) {
                    $line .= ' ' . $row['call'];
                }

                if (isset($row['function'])) {
                    $line .= ' ' . $row['function'];
                }

                return $line;
            }, $trace, array_keys($trace))
        );
    }
}
