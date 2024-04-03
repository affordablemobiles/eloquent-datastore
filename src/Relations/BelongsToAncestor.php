<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Relations;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class BelongsToAncestor extends Relation
{
    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     *
     * @return Collection
     */
    public function get($columns = ['*'])
    {
        if (null === $this->query) {
            return $this->related->newCollection();
        }

        return $this->query->get($columns);
    }

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
        throw new \LogicException('Not Implemented');
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Get the first related record matching the attributes or create it.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Create or update a related record matching the attributes, and fill it with values.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        throw new \LogicException('Not Implemented');
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
        throw new \LogicException('Not Implemented');
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
        throw new \LogicException('Not Implemented');
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
        throw new \LogicException('Not Implemented');
    }

    /**
     * Create a new instance of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes = [])
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Create a Collection of new instances of the related model.
     *
     * @return Collection
     */
    public function createMany(iterable $records)
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        $model = $this->getRelated();

        if (!$model::isIgnoringTouch()) {
            $entity = $this->getResults();
            if (null !== $entity) {
                $entity->updateTimestamps();
                $entity->save();
            }
        }
    }

    /**
     * Add the constraints for a relationship count query.
     */
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery): void
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like whereColumn.
     *
     * @param mixed $columns
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): void
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Create and return an un-saved instance of the related model.
     */
    public function make(array $attributes = []): Model
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Create and return an un-saved instance of the related models.
     *
     * @param iterable $records
     */
    public function makeMany($records): Collection
    {
        throw new \LogicException('Not Implemented');
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $key = $this->getParentKey();
            if (null !== $key) {
                $query = $this->getRelationQuery();

                $query->where($this->getForeignKeyName(), $key);
            } else {
                $this->query = null;
            }
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
        if (null === $this->getParentKey()) {
            return null;
        }

        return $this->query->first() ?: null;
    }

    /**
     * Run a raw update against the base query.
     *
     * @return int
     */
    public function rawUpdate(array $attributes = [])
    {
        throw new \LogicException('Not Implemented');
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
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param string $relation
     *
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        throw new \LogicException('Eager loading is not implemented');
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return '__key__';
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        // Get the key path from the parent model.
        $keyPath = $this->parent->getKey()->path();

        // Check we have more than just a single key
        //  in the key path...
        if (\count($keyPath) <= 1) {
            return null;
        }

        // Return the 2nd to last element in the key path...
        //  The direct ancestor key.
        $keyData = $keyPath[\count($keyPath)-2];

        $key = null;
        if (isset($keyData['id'])) {
            $key = $this->related->getKey($keyData['id']);
        } else {
            $key = $this->related->getKey($keyData['name']);
        }

        return $key;
    }

    /**
     * Set the foreign ID for creating a related model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function setForeignAttributesForCreate(Model $model): void
    {
        throw new \LogicException('Not Implemented');
    }
}
