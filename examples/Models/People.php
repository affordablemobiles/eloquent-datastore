<?php

declare(strict_types=1);

namespace App\Models;

use A1comms\EloquentDatastore\Eloquent\Model;

class People extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * Set this to true if you want to use
     *  auto-generated numberic keys in
     *  Datastore.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The Datastore `kind` to use for this model.
     *
     * @var string
     */
    protected $table = 'laravel-query';

    /**
     * The database connection to use for this model.
     *
     * @var string
     */
    protected $connection = 'datastore';

    /**
     * The "type" of the primary key ID.
     *
     * Choose 'int' for auto-generated numeric keys,
     *  and remember to set `$incrementing = true` above.
     *
     * Choose 'string' if you are using named keys,
     *  and remember to set `$incrementing = false` above.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
    ];
}
