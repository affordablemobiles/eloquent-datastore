<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent\Concerns;

use A1comms\EloquentDatastore\Relations\BelongsToAncestor;
use A1comms\EloquentDatastore\Relations\HasManyDescendants;

trait HasRelationships
{
    /**
     * Define a one-to-many ancestor/descendant relationship.
     */
    public function hasManyDescendants(string $related): HasManyDescendants
    {
        $instance = $this->newRelatedInstance($related);

        return new HasManyDescendants($instance->newQuery(), $this);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param null|string $relation
     */
    public function belongsToAncestor(string $related): BelongsToAncestor
    {
        $instance = $this->newRelatedInstance($related);

        return new BelongsToAncestor($instance->newQuery(), $this);
    }
}
