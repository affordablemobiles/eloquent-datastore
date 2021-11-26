<?php

namespace Appsero\LaravelDatastore\Eloquent;

use Illuminate\Database\Eloquent\Collection as BaseCollection;
use LogicException;

class Collection extends BaseCollection
{
    /**
     * Delete the whole collection.
     */
    public function delete()
    {
        $model = $this->first();

        if (!$model) {
            throw new LogicException('Unable to create query for empty collection.');
        }

        $class = get_class($model);

        $this->each(function ($item) use ($class) {
            if (!$item instanceof $class) {
                throw new LogicException('Unable to create query for collection with mixed types.');
            }
        });

        return $model->newModelQuery()->toBase()->delete($this->modelKeys());
    }
}
