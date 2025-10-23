<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Eloquent;

use AffordableMobiles\EloquentDatastore\Concerns\BuildsQueries;
use AffordableMobiles\EloquentDatastore\Query\Builder as QueryBuilder;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->query->getConnection();
    }

    /**
     * Get Datastore client.
     *
     * @return DatastoreClient
     */
    public function getClient()
    {
        return $this->query->getConnection()->getClient();
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        $this->query->namespace($model->getNamespace());

        return $this;
    }

    /**
     * Return the end cursor from the last query.
     */
    public function lastCursor()
    {
        return $this->query->lastCursor();
    }

    public function find($id, $columns = [])
    {
        if (null === $id) {
            return null;
        }

        $result = $this->query->cacheTags([
            $this->model->getCacheTagForFind($id),
        ])->find(
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

    /**
     * Find multiple models by their primary keys.
     *
     * @param array|\Illuminate\Contracts\Support\Arrayable $ids
     * @param array                                         $columns
     *
     * @return Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->lookup($ids, $columns);
    }

    public function _upsert(array $values, $keys, $options = [])
    {
        return $this->query->_upsert($values, $keys, $options);
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

    /**
     * Get the first record matching the attributes or instantiate it.
     *
     * @return Model|static
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        if (null !== ($instance = $this->where($this->remapWhereForSelect($attributes))->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Get the first record matching the attributes or create it.
     *
     * @return Model|static
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        if (null !== ($instance = $this->where($this->remapWhereForSelect($attributes))->first())) {
            return $instance;
        }

        return tap($this->newModelInstance(array_merge($attributes, $values)), static function ($instance): void {
            $instance->save();
        });
    }

    protected function remapWhereForSelect(array $attributes)
    {
        if (!empty($attributes['id'])) {
            $attributes['__key__'] = $this->model->getKey($attributes['id']);
            unset($attributes['id']);
        }

        return $attributes;
    }
}
