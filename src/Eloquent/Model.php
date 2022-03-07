<?php

namespace Appsero\LaravelDatastore\Eloquent;

use Google\Cloud\Datastore\Key;
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
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * @inheritdoc
     */
    public function getKey($id = false)
    {
        
        if ($id) {
            return $this->getConnection()->getClient()->key(
                $this->getTable(),
                (string)$id,
                [
                    'identifierType' => $this->incrementing ? Key::TYPE_ID : Key::TYPE_NAME,
                ]
            );
        }
        
        if (!isset($this->__key__)) {
            return $this->getConnection()->getClient()->key(
                $this->getTable(),
                (string)$this->id,
                [
                    'identifierType' => $this->incrementing ? Key::TYPE_ID : Key::TYPE_NAME,
                ]
            );
        }
        return $this->__key__;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = $this->attributes;
        unset($attributes['_key']);
        unset($attributes['_keys']);
        unset($attributes['__key__']);
        return $attributes;
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value);
    }

    /**
     * If there is no id attribute then make the key as id.
     */
    public function getIdAttribute($value = null)
    {
        return empty($value) ? ($this->__key__->path()[0]['name'] ?? $this->__key__->path()[0]['id'] ?? '') : $value;
    }

    /**
     * If there is no id attribute then make the key as key property.
     */
    public function getKeyAttribute($value = null)
    {
        return empty($value) ? ($this->__key__->path()[0]['name'] ?? $this->__key__->path()[0]['id'] ?? '') : $value;
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ?
                        $this->performUpsert($query) : true;
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query);

            if (! $this->getConnectionName() &&
                $connection = $query->getConnection()) {
                $this->setConnection($connection->getName());
            }
        }

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method here to run any actions
        // we need to happen after a model gets successfully saved right here.
        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performUpsert(Builder $query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $query->upsert($this->getAttributes(), $this->getKey());

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
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
