<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;

/**
 * Test Model for Datastore Namespaces.
 */
class NamespacedOrder extends Model
{
    public $incrementing = true;

    public $timestamps = false;
    protected $table   = 'namespaced_orders';
    protected $keyType = 'int';

    protected $fillable = [
        'name',
    ];

    public function __construct(array $attributes = [])
    {
        // Dynamically set the namespace for this model.
        $this->namespace = 'test-namespace';

        parent::__construct($attributes);
    }
}
