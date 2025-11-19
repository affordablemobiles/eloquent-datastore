<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\FlushQueryCacheObserver as ParentFlushQueryCacheObserver;

class FlushQueryCacheObserver extends ParentFlushQueryCacheObserver
{
    /**
     * Invalidate the cache for a model.
     *
     * @param null|string     $relation
     * @param null|Collection $pivotedModels
     *
     * @throws \Exception
     */
    protected function invalidateCache(Model $model, $relation = null, $pivotedModels = null): void
    {
        $class = $model::class;

        $tags = $model->getCacheTagsToInvalidateOnUpdate($relation, $pivotedModels);

        if (!$tags) {
            throw new \Exception('Automatic invalidation for '.$class.' works only if at least one tag to be invalidated is specified.');
        }

        $class::flushQueryCache($tags);

        // Re-cache the model for a fetch($id) query, so we don't have to go back to the DB for it.
        //  this is designed for use mainly with the `array` cache driver for inside a single request.
        if ($model->exists) {
            $model->recacheFindQuery();
        }
    }
}
