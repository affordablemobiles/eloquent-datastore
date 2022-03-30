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

        $model = $this->prepareBulkQuery();

        return $model->newQueryWithoutScopes()
            ->lookup($this->modelKeys())
        ;
    }

    /**
     * Update all entities in the DB.
     */
    public function save()
    {
        if ($this->isEmpty()) {
            return true;
        }

        $model = $this->prepareBulkQuery();

        // Prepare the models for update...
        $entities = $this->map(fn ($entity) => $entity->prepareBulkUpsert())->toArray();

        // Remove any empty entries (not dirty)...
        $rEntities = array_filter($entities);

        $resultKeys = $model->newQueryWithoutScopes()->_upsert(
            array_map(fn ($entity) => $entity['attributes'], $rEntities),
            array_map(fn ($entity) => $entity['key'], $rEntities),
            $model->getQueryOptions(),
        );

        $empty = 0;
        // Finish up by applying any returned auto-generated numeric keys
        //  to the models in the collection...
        // This looks complicated as the array of records we actually upserted
        //  in the query might not match our source collection (shorter array length),
        //  as we're only inserting ones that are dirty.
        $this->map(function ($entity, $index) use (&$empty, $entities, $resultKeys): void {
            $id = null;

            if (empty($entities[$index])) {
                ++$empty;
            } else {
                $id = $resultKeys[($index - $empty)]->pathEndIdentifier();
            }

            $entity->finishBulkUpsert($id);
        });

        return true;
    }

    /**
     * Update all entities in the DB:
     *  Alias for $collection->save().
     */
    public function update()
    {
        return $this->save();
    }

    /**
     * Delete the whole collection.
     */
    public function delete()
    {
        if ($this->isEmpty()) {
            return true;
        }

        $model = $this->prepareBulkQuery();

        return $model->newModelQuery()->toBase()->delete($this->modelKeys());
    }

    protected function prepareBulkQuery()
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

        return $model;
    }
}
