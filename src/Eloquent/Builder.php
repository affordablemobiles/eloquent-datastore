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

    public function find($id, $columns = ['*'])
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

    /**
     * Add a basic where clause to the query.
     *
     * @param array|\Closure|string $column
     * @param mixed                 $operator
     * @param mixed                 $value
     * @param string                $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // Get the model's primary key name (e.g., 'id' or 'uuid')
        $keyName = $this->model->getKeyName();

        // Case 1: where(['uuid' => '123', 'name' => 'John'])
        if (\is_array($column)) {
            foreach ($column as $key => $val) {
                if ($key === $keyName) {
                    // Remap this part of the array query
                    $this->whereKey($val, $boolean);
                    unset($column[$keyName]);
                }
            }

            // Let the parent handle the rest of the array (e.g., 'name' => 'John')
            return parent::where($column, $operator, $value, $boolean);
        }

        // Case 2: where('uuid', '123') or where('uuid', '=', '123')
        if (\is_string($column) && $column === $keyName) {
            // We have a query for the primary key.
            // We must convert it to a '__key__' query.
            [$value, $operator] = $this->query->prepareValueAndOperator(
                $value,
                $operator,
                2 === \func_num_args()
            );

            // Build the full Datastore Key for the query
            $datastoreKey = $this->model->getKey($value);

            $this->query->where('__key__', $operator, $datastoreKey, $boolean);

            return $this;
        }

        // Case 3: All other queries (e.g., where('name', ...))
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param array|\Closure|string $column
     * @param mixed                 $operator
     * @param mixed                 $value
     *
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value,
            $operator,
            2 === \func_num_args()
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where key" clause to the query.
     *
     * @param mixed $id
     * @param mixed $boolean
     *
     * @return $this
     */
    public function whereKey($id, $boolean = 'and')
    {
        if ($id instanceof Key) {
            $this->query->where('__key__', '=', $id, $boolean);

            return $this;
        }

        if (\is_array($id) || $id instanceof Arrayable) {
            $keys = [];
            foreach ($id as $rawId) {
                $keys[] = $this->model->getKey($rawId);
            }

            // Datastore does not support 'IN' queries on the key.
            // This is a limitation. We can only support a single key.
            if (\count($keys) > 1) {
                throw new \LogicException('Datastore does not support WHERE KEY IN (...) queries. Use lookup() instead.');
            }

            if (empty($keys)) {
                // Emulate a "where 1=0"
                $this->query->where('__key__', '=', null, $boolean);

                return $this;
            }

            $this->query->where('__key__', '=', $keys[0], $boolean);

            return $this;
        }

        // Handle single raw IDs (e.g., '123' or 'uuid-string')
        return $this->whereKey($this->model->getKey($id), $boolean);
    }

    protected function remapWhereForSelect(array $attributes)
    {
        $keyName = $this->model->getKeyName();

        if (!empty($attributes[$keyName])) {
            $attributes['__key__'] = $this->model->getKey($attributes[$keyName]);
            unset($attributes[$keyName]);
        }

        return $attributes;
    }
}
