<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent;

use A1comms\EloquentDatastore\Helpers\BuildsQueries;
use A1comms\EloquentDatastore\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    use BuildsQueries;

    /**
     * Create a new Eloquent query builder instance.
     */
    public function __construct(QueryBuilder $query)
    {
        parent::__construct($query);
    }

    /**
     * Get Datastore Connection.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->query->getConnection();
    }

    /**
     * Get Datastore client.
     *
     * @return \Google\Cloud\Datastore\DatastoreClient
     */
    public function getClient()
    {
        return $this->query->getConnection()->getClient();
    }

    /**
     * Return the end cursor from the last query.
     */
    public function lastCursor()
    {
        return $this->query->lastCursor();
    }

    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = [])
    {
        $result = $this->query->find(
            $this->model->getKey($id),
            $columns
        );

        if (null === $result) {
            return null;
        }

        return $this->hydrate([
            $result,
        ])->first();
    }
}
