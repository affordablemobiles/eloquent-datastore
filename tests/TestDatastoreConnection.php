<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests;

use AffordableMobiles\EloquentDatastore\DatastoreConnection;

class TestDatastoreConnection extends DatastoreConnection
{
    /**
     * Overridden to instantiate our TestDatastoreClient
     * instead of the real one.
     *
     * @param mixed $config
     */
    public function makeClient($config): self
    {
        $client = new TestDatastoreClient([ // Use our test client
            'transport' => $config['transport'] ?? 'grpc',
        ]);

        return $this->setClient($client);
    }
}
