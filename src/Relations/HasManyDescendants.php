<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Relations;

use A1comms\EloquentDatastore\Eloquent\Collection;
use A1comms\EloquentDatastore\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class HasManyDescendants extends Relation
{
    /**
     * Find a model by its primary key or return a new instance of the related model.
     *
     * @param mixed $id
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection
     */
    public function findOrNew($id, $columns = ['*'])
    {
        if (null === ($instance = $this->find($id, $columns))) {
            $instance = $this->related->newInstance();

            $this->setForeignAttributesForCreate($instance);
        }

        return $instance;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        if (null === ($instance = $this->where($this->remapWhereForSelect($attributes))->first())) {
            $instance = $this->related->newInstance(array_merge($attributes, $values));

            $this->setForeignAttributesForCreate($instance);
        }

        return $instance;
    }

    /**
     * Get the first related record matching the attributes or create it.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        if (null === ($instance = $this->where($this->remapWhereForSelect($attributes))->first())) {
            $instance = $this->create(array_merge($attributes, $values));
        }

        return $instance;
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values): void {
            $instance->fill($values);

            $instance->save();
        });
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return false|\Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        $this->setForeignAttributesForCreate($model);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a model instance without raising any events to the parent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return false|\Illuminate\Database\Eloquent\Model
     */
    public function saveQuietly(Model $model)
    {
        return Model::withoutEvents(fn () => $this->save($model));
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param iterable $models
     *
     * @return iterable
     */
    public function saveMany($models)
    {
        foreach ($models as &$model) {
            // Only set the attributes here,
            // don't actually save individually...
            $this->setForeignAttributesForCreate($model);
        }

        // Perform a bulk upsert for better performance
        // on Datastore...
        $modelCollection = $this->related->newCollection((array) $models);
        $modelCollection->save();

        return $modelCollection;
    }

    /**
     * Create a new instance of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance): void {
            $this->setForeignAttributesForCreate($instance);

            $instance->save();
        });
    }

    /**
     * Create a Collection of new instances of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createMany(iterable $records)
    {
        // Create a new instance & fill it with the attributes
        // and ancestor data, but don't save it individually.
        $modelCollection = $this->makeMany($records);

        // Perform a bulk upsert for better performance
        // on Datastore...
        $modelCollection->save();

        return $modelCollection;
    }

    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        $model = $this->getRelated();

        if (!$model::isIgnoringTouch()) {
            // Load in the relations:
            //  This is NoSQL after all, so we can't
            //  do a single column update without
            //  loading in the whole record first.
            $modelCollection = $this->getResults();
            // Touch the timestamps but don't
            // update anything individually...
            $modelCollection->map(fn ($entity) => $entity->updateTimestamps());
            // Perform a bulk upsert for better performance
            // on Datastore...
            $modelCollection->save();
        }
    }

    /**
     * Add the constraints for a relationship count query.
     */
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param mixed $columns
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Create and return an un-saved instance of the related model.
     */
    public function make(array $attributes = []): Model
    {
        return tap($this->related->newInstance($attributes), function ($instance): void {
            $this->setForeignAttributesForCreate($instance);
        });
    }

    /**
     * Create and return an un-saved instance of the related models.
     *
     * @param iterable $records
     */
    public function makeMany($records): Collection
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->make($record));
        }

        return $instances;
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $query = $this->getRelationQuery();

            $query->hasAncestor($this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        throw new \LogicException('Eager loading is not implemented');
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return null !== $this->getParentKey()
                ? $this->query->get()
                : $this->related->newCollection();
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string                                   $relation
     *
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        throw new \LogicException('Eager loading is not implemented');
    }

    /**
     * Set the foreign ID for creating a related model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function setForeignAttributesForCreate(Model $model): void
    {
        $model->setAttribute($this->getForeignKeyName(), $this->getParentKey());
    }

    protected function remapWhereForSelect(array $attributes)
    {
        if (!empty($attributes['id'])) {
            $attributes['__key__'] = $this->related->getKey($attributes['id']);
            unset($attributes['id']);
        }

        return $attributes;
    }
}
