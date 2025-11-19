<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore;

use Illuminate\Support\ServiceProvider;

class DatastoreServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->registerDatastoreDriver();
    }

    /**
     * Register the Datastore database driver.
     * This method is separated to allow for easy extension in tests.
     */
    protected function registerDatastoreDriver(): void
    {
        $this->app->resolving('db', function ($db): void {
            $db->extend('datastore', function ($config, $name) {
                $config['name'] = $name;

                return $this->getDatastoreConnection($config);
            });
        });
    }

    /**
     * Factory method for creating the DatastoreConnection.
     */
    protected function getDatastoreConnection(array $config): DatastoreConnection
    {
        return new DatastoreConnection($config);
    }
}
