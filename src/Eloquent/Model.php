<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent;

use A1comms\EloquentDatastore\Helpers\ModelHelper;
use A1comms\EloquentDatastore\Query\Builder as QueryBuilder;
use DateTimeInterface;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;

abstract class Model extends BaseModel
{
    use ModelHelper;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Set default connection to datastore.
     *
     * @var string
     */
    protected $connection = 'datastore';

    /**
     * A list of attributes to exclude from the default indexing strategy.
     *
     * @var string
     */
    protected $excludeFromIndexes = [];

    /**
     * The primary key for the datastore should be "id".
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
     * {@inheritdoc}
     */
    public function getKey($id = false)
    {
        if ($id) {
            return $this->getConnection()->getClient()->key(
                $this->getTable(),
                (string) $id,
                [
                    'identifierType' => $this->incrementing ? Key::TYPE_ID : Key::TYPE_NAME,
                ]
            );
        }

        if (!isset($this->attributes['__key__'])) {
            return $this->getConnection()->getClient()->key(
                $this->getTable(),
                (string) $this->attributes['id'],
                [
                    'identifierType' => $this->incrementing ? Key::TYPE_ID : Key::TYPE_NAME,
                ]
            );
        }

        return $this->attributes['__key__'];
    }

    /**
     * Get all of the current attributes on the model.
     */
    public function getAttributes(): array
    {
        $this->mergeAttributesFromCachedCasts();

        $attributes = $this->attributes;
        unset($attributes['_key'], $attributes['_keys'], $attributes['__key__']);

        return $attributes;
    }

    /**
     * Store DateTime as a DateTime object (instead of converting to string).
     *
     * @param mixed $value
     */
    public function fromDateTime($value): DateTimeInterface
    {
        return empty($value) ? $value : $this->asDateTime($value);
    }

    /**
     * If there is no id attribute then make the key as id.
     *
     * @param null|mixed $value
     */
    public function getIdAttribute($value = null)
    {
        return empty($value) ? ($this->__key__->path()[0]['name'] ?? $this->__key__->path()[0]['id'] ?? '') : $value;
    }

    /**
     * If there is no id attribute then make the key as key property.
     *
     * @param null|mixed $value
     */
    public function getKeyAttribute($value = null)
    {
        return empty($value) ? ($this->__key__->path()[0]['name'] ?? $this->__key__->path()[0]['id'] ?? '') : $value;
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        $this->mergeAttributesFromCachedCasts();

        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if (false === $this->fireModelEvent('saving')) {
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

            if (!$this->getConnectionName()
                && $connection = $query->getConnection()) {
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
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * {@inheritdoc}
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    /**
     * Perform a model insert operation.
     *
     * @return bool
     */
    protected function performInsert(BaseBuilder $query)
    {
        if (false === $this->fireModelEvent('creating')) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->getAttributesForInsert();

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        }

        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($attributes)) {
                return true;
            }

            $query->insert($attributes, $this->getQueryOptions());
        }

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param array $attributes
     */
    protected function insertAndSetId(BaseBuilder $query, $attributes): void
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName(), $this->getQueryOptions());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Perform a model update operation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return bool
     */
    protected function performUpsert(Builder $query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if (false === $this->fireModelEvent('updating')) {
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

        if (\count($dirty) > 0) {
            $query->_upsert($this->getAttributes(), $this->getKey(), $this->getQueryOptions());

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Get options for the DatastoreClient.
     */
    protected function getQueryOptions(): array
    {
        return [
            'excludeFromIndexes' => $this->excludeFromIndexes,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection);
    }
}
