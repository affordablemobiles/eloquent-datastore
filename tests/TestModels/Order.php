<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

/**
 * Test Model for JSON/Array casting.
 */
class Order extends Model
{
    public $incrementing = true;

    public $timestamps = false;
    protected $table   = 'orders';
    protected $keyType = 'int';

    protected $fillable = [
        'details',
    ];

    protected $casts = [
        'details' => AsArrayObject::class,
    ];

    protected $excludeFromIndexes = [
        'details',
    ];
}
