<?php

namespace Appsero\LaravelDatastore\Eloquent;

use Appsero\LaravelDatastore\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    /**
     * Create a new Eloquent query builder instance.
     *
     * @param QueryBuilder $query
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
     * @inheritdoc
     */
    public function find($id, $columns = [])
    {
        $result = $this->query->find(
            $this->model->getKey($id),
            $columns
        );

        if (is_null($result)) {
            return null;
        }

        return $this->hydrate([
            $result
        ])->first();
    }
}
