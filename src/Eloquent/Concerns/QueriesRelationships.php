<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent\Concerns;

use Closure;

trait QueriesRelationships
{
    /**
     * Add a relationship count / exists condition to the query.
     *
     * @param mixed $relation
     * @param mixed $operator
     * @param mixed $count
     * @param mixed $boolean
     */
    public function has($relation, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a polymorphic relationship count / exists condition to the query.
     *
     * @param mixed $relation
     * @param mixed $types
     * @param mixed $operator
     * @param mixed $count
     * @param mixed $boolean
     */
    public function hasMorph($relation, $types, $operator = '>=', $count = 1, $boolean = 'and', Closure $callback = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a basic where clause to a relationship query.
     *
     * @param mixed      $relation
     * @param mixed      $column
     * @param null|mixed $operator
     * @param null|mixed $value
     */
    public function whereRelation($relation, $column, $operator = null, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add an "or where" clause to a relationship query.
     *
     * @param mixed      $relation
     * @param mixed      $column
     * @param null|mixed $operator
     * @param null|mixed $value
     */
    public function orWhereRelation($relation, $column, $operator = null, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a polymorphic relationship condition to the query with a where clause.
     *
     * @param mixed      $relation
     * @param mixed      $types
     * @param mixed      $column
     * @param null|mixed $operator
     * @param null|mixed $value
     */
    public function whereMorphRelation($relation, $types, $column, $operator = null, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a polymorphic relationship condition to the query with an "or where" clause.
     *
     * @param mixed      $relation
     * @param mixed      $types
     * @param mixed      $column
     * @param null|mixed $operator
     * @param null|mixed $value
     */
    public function orWhereMorphRelation($relation, $types, $column, $operator = null, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a morph-to relationship condition to the query.
     *
     * @param mixed $relation
     * @param mixed $model
     * @param mixed $boolean
     */
    public function whereMorphedTo($relation, $model, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add a "belongs to" relationship where clause to the query.
     *
     * @param mixed      $related
     * @param null|mixed $relationshipName
     * @param mixed      $boolean
     */
    public function whereBelongsTo($related, $relationshipName = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    /**
     * Add subselect queries to include an aggregate value for a relationship.
     *
     * @param mixed      $relations
     * @param mixed      $column
     * @param null|mixed $function
     */
    public function withAggregate($relations, $column, $function = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }
}
