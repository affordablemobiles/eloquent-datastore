<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Eloquent\Concerns;

use AffordableMobiles\EloquentDatastore\Query\Builder as QueryBuilder;
use AffordableMobiles\EloquentDatastore\Query\Processor;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\Eloquent\Collection;
use Rennokki\QueryCache\Traits\QueryCacheable as ParentQueryCacheable;

trait QueryCacheable
{
    use ParentQueryCacheable {
        ParentQueryCacheable::newBaseQueryBuilder as parentNewBaseQueryBuilder;
        ParentQueryCacheable::getCacheTagsToInvalidateOnUpdate as parentGetCacheTagsToInvalidateOnUpdate;
        ParentQueryCacheable::getFlushQueryCacheObserver as parentGetFlushQueryCacheObserver;
    }

    /**
     * When invalidating automatically on update, you can specify
     * which tags to invalidate.
     *
     * @param null|string     $relation
     * @param null|Collection $pivotedModels
     */
    public function getCacheTagsToInvalidateOnUpdate($relation = null, $pivotedModels = null): array
    {
        return array_merge(
            [
                $this->getCacheTagForFind(),
            ],
            $this->getCacheBaseTags(),
        );
    }

    /**
     * Get a cache tag identifying a single record by ID.
     *
     * @param null|Key $id the scalar ID or full Key object
     */
    public function getCacheTagForFind(?Key $id = null): string
    {
        $key = null;

        if ($id instanceof Key) {
            // We were given a full Key object (e.g., from find(Key $key))
            $key = $id;
        } elseif (null !== $id) {
            // We were given a scalar ID, build the Key from it.
            // This correctly assumes a root entity, matching Model::find() behavior.
            $key = $this->getKey($id);
        } else {
            // $id was null, get the Key from the model instance itself
            // (e.g., during recacheFindQuery)
            $key = $this->getKey();
        }

        // Always serialize the full path for a unique, consistent identifier
        $keyIdentifier = 'key:'.json_encode(
            Processor::normalizeKeyPath(
                $key->path()
            )
        );

        return (string) static::class.':'.$keyIdentifier;
    }

    /**
     * Re-cache the model for a fetch($id) query.
     */
    public function recacheFindQuery()
    {
        // Get the full Key object. This is the most reliable
        // identifier for caching, as it includes ancestors.
        $keyObject = $this->getKey();

        if (!$keyObject) {
            return false; // Should not happen
        }

        $query = $this->newModelQuery();

        $ancestorKey = Processor::getParentKey($keyObject);
        if ($ancestorKey) {
            $query->hasAncestor($ancestorKey);
        }

        // This is now consistent:
        // 1. getCacheTagForFind() will serialize the $keyObject path.
        // 2. recacheFindQuery() in QueryCacheModule will also serialize the $keyObject path.
        return $query->cacheTags([
            $this->getCacheTagForFind($keyObject),
        ])->recacheFindQuery(
            $keyObject,
            array_merge($this->attributes, ['id' => $this->getDatastoreKeyIdentifier()]),
        );
    }

    /**
     * Get the observer class name that will
     * observe the changes and will invalidate the cache
     * upon database change.
     *
     * @return string
     */
    protected static function getFlushQueryCacheObserver()
    {
        return FlushQueryCacheObserver::class;
    }

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        $builder = new QueryBuilder($connection);

        $builder->dontCache();

        if ($this->cacheFor) {
            $builder->cacheFor($this->cacheFor);
        }

        if (method_exists($this, 'cacheForValue')) {
            $builder->cacheFor($this->cacheForValue($builder));
        }

        if ($this->cacheTags) {
            $builder->cacheTags($this->cacheTags);
        }

        if (method_exists($this, 'cacheTagsValue')) {
            $builder->cacheTags($this->cacheTagsValue($builder));
        }

        if ($this->cachePrefix) {
            $builder->cachePrefix($this->cachePrefix);
        }

        if (method_exists($this, 'cachePrefixValue')) {
            $builder->cachePrefix($this->cachePrefixValue($builder));
        }

        if ($this->cacheDriver) {
            $builder->cacheDriver($this->cacheDriver);
        }

        if (method_exists($this, 'cacheDriverValue')) {
            $builder->cacheDriver($this->cacheDriverValue($builder));
        }

        if ($this->cacheUsePlainKey) {
            $builder->withPlainKey();
        }

        if (method_exists($this, 'cacheUsePlainKeyValue')) {
            $builder->withPlainKey($this->cacheUsePlainKeyValue($builder));
        }

        return $builder->cacheBaseTags($this->getCacheBaseTags());
    }
}
