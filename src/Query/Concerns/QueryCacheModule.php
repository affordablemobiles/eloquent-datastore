<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Query\Concerns;

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
     * Get the cache key.
     * Overridden to accept a Key object for the $id parameter.
     */
    public function getCacheKey(string $method = 'get', ?Key $id = null): string
    {
        return $this->getCachePrefix().':'.$this->generatePlainCacheKey($method, $id);
    }

    /**
     * Get the cache from the current query.
     *
     * @param null|Key|string $id the Key object or scalar ID for find, null otherwise
     *
     * @return mixed
     */
    public function getFromQueryCache(string $method = 'get', array $columns = ['*'], Key|string|null $id = null)
    {
        if (null === $this->columns) {
            $this->columns = $columns;
        }

        $key      = $this->getCacheKey($method, $id);
        $cache    = $this->getCache();
        $callback = $this->getQueryCacheCallback($method, $columns, $id);
        $time     = $this->getCacheFor();

        if ($time instanceof \DateTimeInterface || (is_numeric($time) && $time > 0)) {
            return $cache->remember($key, $time, $callback);
        }

        return $cache->rememberForever($key, $callback);
    }

    /**
     * Get the query cache callback.
     *
     * @param array|string    $columns
     * @param null|Key|string $id      the Key object or scalar ID for find, null otherwise
     *
     * @return \Closure
     */
    public function getQueryCacheCallback(string $method = 'get', $columns = ['*'], Key|string|null $id = null)
    {
        return function () use ($method, $columns, $id) {
            $this->avoidCache = true;

            // the function for find() caching accepts different params
            if ('find' === $method) {
                return $this->find($id, $columns);
            }

            return $this->{$method}($columns);
        };
    }

    /**
     * Find a model by its primary key.
     * Overridden for Query Caching.
     *
     * @param array $columns
     * @param mixed $key
     *
     * @return mixed
     */
    public function find($key, $columns = ['*'])
    {
        if (null !== $key) {
            $key = $this->getKey($key);
        }

        if (!$this->shouldAvoidCache()) {
            return $this->getFromQueryCache('find', Arr::wrap($columns), $key);
        }

        return $this->lookup($key, $columns);
    }

    /**
     * Re-cache the model for a find($id) query.
     *
     * @param Key|string $id         the Key object or scalar ID
     * @param array      $attributes the attributes to cache
     *
     * @return mixed
     */
    public function recacheFindQuery(Key $id, array $attributes)
    {
        if (null === $this->columns) {
            $this->columns = ['*'];
        }

        $key   = $this->getCacheKey('find', $id);
        $cache = $this->getCache();

        $callback = static fn () => $attributes;
        $time     = $this->getCacheFor();

        if ($time instanceof \DateTimeInterface || (is_numeric($time) && $time > 0)) {
            return $cache->remember($key, $time, $callback);
        }

        return $cache->rememberForever($key, $callback);
    }

    /**
     * Generate the plain unique cache key for the query.
     *
     * @param null|Key|string $id the Key object or scalar ID for find, null otherwise
     */
    public function generatePlainCacheKey(string $method = 'get', Key|string|null $id = null, ?string $appends = null): string
    {
        $name = $this->connection->getName();

        $keyIdentifierString = 'null';
        if ('find' === $method && null !== $id) {
            $id = $this->getKey($id);

            // Serialize the full path for uniqueness, including ancestors.
            $keyIdentifierString = 'key:'.json_encode($id->path());
        }
        // If method is not 'find', $id should be null, keep 'null' string.

        // Include the serialized key path string in the cache key.
        return $name.':'.$method.':'.$keyIdentifierString.':'.serialize([
            $this->columns,
            $this->offset,
            $this->limit,
            $this->keysOnly,
            $this->ancestor instanceof Key ? json_encode($this->ancestor->path()) : false, // Serialize ancestor too
            $this->namespaceId,
            $this->startCursor,
            $this->distinct,
            // Note: Where clauses might contain Key objects, PHP's serialize should handle this okay
            $this->wheres,
            $this->orders,
        ]).serialize($this->getBindings()).$appends; // Bindings likely empty
    }
}
