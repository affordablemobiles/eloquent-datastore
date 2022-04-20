<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\FlushQueryCacheObserver as ParentFlushQueryCacheObserver;

class FlushQueryCacheObserver extends ParentFlushQueryCacheObserver
{
    /**
     * Invalidate the cache for a model.
     *
     * @param null|string                                   $relation
     * @param null|\Illuminate\Database\Eloquent\Collection $pivotedModels
     *
     * @throws Exception
     */
    protected function invalidateCache(Model $model, $relation = null, $pivotedModels = null): void
    {
        $class = $model::class;

        $tags = $model->getCacheTagsToInvalidateOnUpdate($relation, $pivotedModels);

        if (!$tags) {
            throw new Exception('Automatic invalidation for '.$class.' works only if at least one tag to be invalidated is specified.');
        }

        $class::flushQueryCache($tags);

        $class::cacheTags([
            $model->getCacheTagForFind(),
        ])->recacheFetchQuery($model->id, $model->attributes);
    }
}
