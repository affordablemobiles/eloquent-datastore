<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;
use AffordableMobiles\EloquentDatastore\Relations\HasManyDescendants;

/**
 * Test Model with default 'id' primary key.
 */
class User extends Model
{
    /**
     * Cache queries for 10 minutes.
     *
     * @var \DateTime|int
     */
    public $cacheFor = 600;

    public $timestamps = false;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'age',
    ];

    public function posts(): HasManyDescendants
    {
        return $this->hasManyDescendants(Post::class);
    }
}
