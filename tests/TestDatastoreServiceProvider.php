<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests;

use AffordableMobiles\EloquentDatastore\DatastoreConnection;
use AffordableMobiles\EloquentDatastore\DatastoreServiceProvider;

class TestDatastoreServiceProvider extends DatastoreServiceProvider
{
    /**
     * Overridden factory method to return the TestDatastoreConnection.
     */
    protected function getDatastoreConnection(array $config): DatastoreConnection
    {
        return new TestDatastoreConnection($config);
    }
}
