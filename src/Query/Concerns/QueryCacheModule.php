<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Query\Concerns;

use Google\Cloud\Datastore\Key;
use Illuminate\Support\Arr;
use Rennokki\QueryCache\Traits\QueryCacheModule as ParentQueryCacheModule;

trait QueryCacheModule
{
    use ParentQueryCacheModule {
        ParentQueryCacheModule::generatePlainCacheKey as parentGeneratePlainCacheKey;
        ParentQueryCacheModule::getFromQueryCache as parentGetFromQueryCache;
        ParentQueryCacheModule::getQueryCacheCallback as parentGetQueryCacheCallback;
    }

    /**
     * Get the cache from the current query.
     *
     * @return array
     */
    public function getFromQueryCache(string $method = 'get', array $columns = ['*'], string|Key $id = null)
    {
        if (null === $this->columns) {
            $this->columns = $columns;
        }

        $key      = $this->getCacheKey($method, $id instanceof Key ? ($id->path()[0]['name'] ?? $id->path()[0]['id'] ?? throw new \LogicException('invalid key')) : $id);
        $cache    = $this->getCache();
        $callback = $this->getQueryCacheCallback($method, $columns, $id);
        $time     = $this->getCacheFor();

        if ($time instanceof DateTime || $time > 0) {
            return $cache->remember($key, $time, $callback);
        }

        return $cache->rememberForever($key, $callback);
    }

    /**
     * Get the query cache callback.
     *
     * @param array|string $columns
     * @param null|string  $id
     *
     * @return \Closure
     */
    public function getQueryCacheCallback(string $method = 'get', $columns = ['*'], string|Key $id = null)
    {
        return function () use ($method, $columns, $id) {
            $this->avoidCache = true;

            // the function for find() caching
            // accepts different params
            if ('find' === $method) {
                return $this->find($id, $columns);
            }

            return $this->{$method}($columns);
        };
    }

    public function find($id, $columns = ['*'])
    {
        // implementing the same logic
        if (!$this->shouldAvoidCache()) {
            return $this->getFromQueryCache('find', Arr::wrap($columns), $id);
        }

        return $this->lookup($id, $columns);
    }

    /**
     * Re-cache the model for a find($id) query, so we don't have to go back to the DB for it.
     *  this is designed for use mainly with the `array` cache driver for inside a single request.
     */
    public function recacheFindQuery(string $id, array $attributes)
    {
        if (null === $this->columns) {
            $this->columns = ['*'];
        }

        $key   = $this->getCacheKey('find', $id);
        $cache = $this->getCache();

        $callback = fn () => $attributes;

        $time = $this->getCacheFor();

        if ($time instanceof DateTime || $time > 0) {
            return $cache->remember($key, $time, $callback);
        }

        return $cache->rememberForever($key, $callback);
    }

    /**
     * Generate the plain unique cache key for the query.
     */
    public function generatePlainCacheKey(string $method = 'get', string $id = null, string $appends = null): string
    {
        $name = $this->connection->getName();

        return $name.$method.$id.serialize([
            $this->columns,
            $this->offset,
            $this->limit,
            $this->keysOnly,
            $this->ancestor,
            $this->startCursor,
            $this->distinct,
            $this->wheres,
            $this->orders,
        ]).serialize($this->getBindings()).$appends;
    }
}
