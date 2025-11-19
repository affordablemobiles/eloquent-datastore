<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatastore;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        // 1. Boot the Laravel application
        parent::setUp();

        // 2. If the test class has opted in, refresh the datastore.
        // This now runs *after* the DB facade is available.
        $this->refreshDatastore();
    }

    /**
     * Get package providers.
     *
     * @param Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TestDatastoreServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set the default database connection to 'datastore'
        $app['config']->set('database.default', 'datastore');

        $transport = \extension_loaded('grpc') && \extension_loaded('protobuf')
            ? 'grpc'
            : 'rest';

        // Configure the 'datastore' connection
        // The project_id and emulator_host are set via environment variables
        // in phpunit.xml and are read automatically by the Google Datastore client.
        $config = [
            'driver'      => 'datastore',
            'transport'   => env('DATASTORE_TRANSPORT', $transport), // 'grpc' or 'rest'
        ];

        $app['config']->set('database.connections.datastore', $config);
    }
}
