<?php

namespace Appsero\LaravelDatastore\Eloquent;

use Appsero\LaravelDatastore\Helpers\ModelHelper;
use Appsero\LaravelDatastore\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;

abstract class Model extends BaseModel
{
    use ModelHelper;

    /**
     * Set default connection to datastore.
     *
     * @var string
     */
    protected $connection = 'datastore';

    /**
     * The primary key for the datastore should be __key__.
     *
     * @var string
     */
    protected $primaryKey = '__key__';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'object';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @inheritdoc
     */
    public function getKey()
    {
        return $this->__key__;
    }

    /**
     * If there is no id attribute then make the key as id.
     */
    public function getIdAttribute($value = null)
    {
        return empty($value) ? $this->__key__ : $value;
    }

    /**
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * @inheritdoc
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection);
    }

    /**
     * @inheritdoc
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }
}
