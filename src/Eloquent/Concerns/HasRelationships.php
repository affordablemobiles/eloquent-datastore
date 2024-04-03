<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Eloquent\Concerns;

use AffordableMobiles\EloquentDatastore\Relations\BelongsToAncestor;
use AffordableMobiles\EloquentDatastore\Relations\HasManyDescendants;

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
     */
    public function belongsToAncestor(string $related): BelongsToAncestor
    {
        $instance = $this->newRelatedInstance($related);

        return new BelongsToAncestor($instance->newQuery(), $this);
    }
}
