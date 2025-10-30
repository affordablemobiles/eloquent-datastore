<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;

/**
 * Test Model for Query Caching.
 */
class BasketCached extends Model
{
    public $incrementing = true;

    /**
     * Cache queries for 10 minutes.
     *
     * @var \DateTime|int
     */
    public $cacheFor = 600;

    public $timestamps = false;
    protected $table   = 'baskets';
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'tariff_id',
    ];

    protected $excludeFromIndexes = [
        'tariff_id',
    ];
}
