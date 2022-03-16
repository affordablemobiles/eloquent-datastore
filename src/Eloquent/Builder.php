<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent;

use A1comms\EloquentDatastore\Helpers\BuildsQueries;
use A1comms\EloquentDatastore\Query\Builder as QueryBuilder;
use Google\Cloud\Datastore\Key;
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

    public function lookup($key, $columns = [])
    {
        if (\is_array($key)) {
            $key = array_map(fn ($id) => $id instanceof Key ? $id : $this->model->getKey($id), $key);

            $results = $this->query->lookup($key, $columns);

            return $this->hydrate($results);
        }

        $key = $key instanceof Key ? $key : $this->model->getKey($key);

        $result = $this->query->find(
            $key,
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
