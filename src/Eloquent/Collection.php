<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent;

use Illuminate\Database\Eloquent\Collection as BaseCollection;
use LogicException;

class Collection extends BaseCollection
{
    /**
     * Reload a fresh model instance from the database for all the entities.
     *
     * @param array<array-key, string>|string $with
     *
     * @return static
     */
    public function fresh($with = [])
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $model = $this->first();

        return $model->newQueryWithoutScopes()
            ->lookup($this->modelKeys())
        ;
    }

    /**
     * Delete the whole collection.
     */
    public function delete()
    {
        $model = $this->first();

        if (!$model) {
            throw new LogicException('Unable to create query for empty collection.');
        }

        $class = $model::class;

        $this->each(function ($item) use ($class): void {
            if (!$item instanceof $class) {
                throw new LogicException('Unable to create query for collection with mixed types.');
            }
        });

        return $model->newModelQuery()->toBase()->delete($this->modelKeys());
    }
}
