<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;
use AffordableMobiles\EloquentDatastore\Relations\BelongsToAncestor;

/**
 * Test Model for ancestor/descendant relationships.
 */
class Post extends Model
{
    /**
     * Cache queries for 10 minutes.
     *
     * @var \DateTime|int
     */
    public $cacheFor = 600;

    public $timestamps = false;

    protected $table = 'posts';

    protected $fillable = [
        'title',
    ];

    public function user(): BelongsToAncestor
    {
        return $this->belongsToAncestor(User::class);
    }
}
