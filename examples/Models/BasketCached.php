<?php

declare(strict_types=1);

namespace App\Models;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;

class BasketCached extends Model
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
     * Specify the amount of time to cache queries.
     * Do not specify or set it to null to disable caching.
     *
     * @var \DateTime|int
     */
    public $cacheFor = -1;

    /**
     * The Datastore `kind` to use for this model.
     *
     * @var string
     */
    protected $table = 'laravel-basket';

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
        'handset_id',
        'tariff_id',
        'adnetwork',
        'campaign',
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
        'handset_id',
        'tariff_id',
        'adnetwork',
        'campaign',
    ];
}
