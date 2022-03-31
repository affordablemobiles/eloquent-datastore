<?php

declare(strict_types=1);

namespace App\Models;

use A1comms\EloquentDatastore\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Order extends Model
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
    protected $table = 'eloquent-order';

    /**
     * The database connection to use for this model.
     *
     * @var string
     */
    protected $connection = 'datastore';

    /**
     * A list of attributes to exclude from the default indexing strategy.
     *
     * Very important with Datastore, as too many index will slow down
     * your writes & use a lot of storage space.
     *
     * Note: anything you include in a filter or orderBy clause
     *  needs an index, everything else should be in here.
     *
     * @var string
     */
    protected $excludeFromIndexes = [
        'details',
    ];

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
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'details' => AsArrayObject::class,
    ];

    /**
     * Create a new Eloquent model instance.
     */
    public function __construct(array $attributes = [])
    {
        // Set the Datastore namespace,
        //  to something dynamically determined at runtime.
        //  This is a pretty poor example of a namespaceId value,
        //  but hopefully you get the idea.
        $this->namespace = gmdate('Y-m-d');

        parent::__construct($attributes);
    }
}
