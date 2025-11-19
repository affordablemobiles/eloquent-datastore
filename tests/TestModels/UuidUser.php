<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests\TestModels;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;

/**
 * Test Model with a custom 'uuid' primary key.
 */
class UuidUser extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public $timestamps = false;

    protected $table   = 'uuid_users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
    ];
}
