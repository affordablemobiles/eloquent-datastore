<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Eloquent\Concerns;

use AffordableMobiles\EloquentDatastore\Query\Builder as QueryBuilder;
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
     * @param null|mixed $id
     */
    public function getCacheTagForFind($id = null): string
    {
        if (null === $id) {
            $id = $this->id;
        }

        return (string) static::class.':'.(string) $id;
    }

    /**
     * Re-cache the model for a fetch($id) query, so we don't have to go back to the DB for it.
     *  this is designed for use mainly with the `array` cache driver for inside a single request.
     */
    public function recacheFindQuery()
    {
        return $this->newModelQuery()->cacheTags([
            $this->getCacheTagForFind(),
        ])->recacheFindQuery($this->id, $this->attributes);
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
