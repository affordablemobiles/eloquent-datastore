<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Eloquent;

use A1comms\EloquentDatastore\Query\Builder as QueryBuilder;
use Carbon\CarbonInterval;
use DateTimeInterface;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;

abstract class Model extends BaseModel
{
    use Concerns\HasRelationships;
    use Concerns\QueriesRelationships;
    use Concerns\QueryCacheable;

    /**
     * The name of the "expire at" column.
     *
     * @var null|string
     */
    public const EXPIRE_AT = 'expire_at';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The cache driver to be used.
     *
     * @var string
     */
    public $cacheDriver = 'array';

    /**
     * Invalidate the cache automatically
     * upon update in the database.
     *
     * @var bool
     */
    protected static $flushCacheOnUpdate = true;

    /**
     * Set default connection to datastore.
     *
     * @var string
     */
    protected $connection = 'datastore';

    /**
     * The Datastore `namespace` to use for this model.
     *  null / unspecified means it'll be the default.
     *
     * @var null|string
     */
    protected $namespace;

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
                    'namespaceId'    => $this->namespace,
                ]
            );
        }

        $key = null;
        if ((!$this->exists) && $this->incrementing) {
            // If the record doesn't yet exist
            // and we are using auto generated keys,
            // return an incomplete key.
            $key = $this->getConnection()->getClient()->key(
                $this->getTable(),
                null,
                [
                    'identifierType' => Key::TYPE_ID,
                    'namespaceId'    => $this->namespace,
                ]
            );
        } elseif (isset($this->attributes['__key__'])) {
            return $this->attributes['__key__'];
        } else {
            $key = $this->getConnection()->getClient()->key(
                $this->getTable(),
                (string) $this->attributes['id'],
                [
                    'identifierType' => $this->incrementing ? Key::TYPE_ID : Key::TYPE_NAME,
                    'namespaceId'    => $this->namespace,
                ]
            );
        }

        if (isset($this->attributes['__parent__'])) {
            $key->ancestorKey($this->attributes['__parent__']);
        }

        return $key;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get all of the current attributes on the model.
     */
    public function getAttributes(): array
    {
        $this->mergeAttributesFromCachedCasts();

        $attributes = $this->attributes;
        unset($attributes['_key'], $attributes['_keys'], $attributes['__key__'], $attributes['__parent__']);

        return $attributes;
    }

    public function delete(): bool
    {
        if (null === $this->getKeyName()) {
            throw new LogicException('No primary key defined on model.');
        }

        if (!$this->exists) {
            return false;
        }

        if (false === $this->fireModelEvent('deleting')) {
            return false;
        }

        $this->newModelQuery()->getQuery()->delete($this->getKey());

        $this->exists = false;

        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param array|\Illuminate\Support\Collection|int|string $ids
     *
     * @return int
     */
    public static function destroy($ids)
    {
        if ($ids instanceof EloquentCollection) {
            $ids = $ids->modelKeys();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = \is_array($ids) ? $ids : \func_get_args();

        if (0 === \count($ids)) {
            return 0;
        }

        $records = $this->newModelQuery()->lookup($ids, ['__key__']);

        $records->delete();

        return \count($ids);
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

    public function prepareBulkUpsert()
    {
        if (!$this->exists) {
            return $this->prepareBulkInsert();
        }

        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if (false === $this->fireModelEvent('updating')) {
            return false;
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (\count($dirty) > 0) {
            // First we need to create a fresh query instance and touch the creation and
            // update timestamp on the model which are maintained by us for developer
            // convenience. Then we will just continue saving the model instances.
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            return [
                'key'        => $this->getKey(),
                'attributes' => $this->getAttributes(),
            ];
        }

        return [];
    }

    public function prepareBulkInsert()
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
            return [
                'key'        => $this->getKey(),
                'attributes' => $attributes,
            ];
        }

        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.

        if (empty($attributes)) {
            return [];
        }

        return [
            'key'        => $this->getKey(),
            'attributes' => $attributes,
        ];
    }

    public function finishBulkUpsert($id = null)
    {
        if (!$this->exists) {
            return $this->finishBulkInsert($id);
        }

        $dirty = $this->getDirty();

        if (\count($dirty) > 0) {
            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    public function finishBulkInsert($id = null)
    {
        if ($this->getIncrementing()) {
            if (!empty($id)) {
                // Set the auto generated key ID on a bulk insert...
                $this->setAttribute($this->getKeyName(), $id);
            }
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
     * Get options for the DatastoreClient.
     */
    public function getQueryOptions(): array
    {
        return [
            'excludeFromIndexes' => $this->excludeFromIndexes,
        ];
    }

    /**
     * Reload a fresh model instance from the database.
     *
     * @param array|string $with
     *
     * @return null|static
     */
    public function fresh($with = [])
    {
        if (!$this->exists) {
            return;
        }

        if (!empty($with)) {
            throw new \LogicException('$with attribute unsupported');
        }

        $query = $this->newModelQuery();

        $query->flushQueryCacheWithTag(
            $this->getCacheTagForFind()
        );

        return $query->find($this->id);
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     *
     * @return $this
     */
    public function refresh()
    {
        if (!$this->exists) {
            return $this;
        }

        $query = $this->newBaseQueryBuilder();

        $query->flushQueryCacheWithTag(
            $this->getCacheTagForFind()
        );

        $this->setRawAttributes(
            $query->find(
                $this->getKey()
            )
        );

        $this->syncOriginal();

        return $this;
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return $this
     */
    public function updateTimestamps()
    {
        $expireAfter = $this->getExpireAfterInterval();

        if (null !== $expireAfter && $expireAfter instanceof CarbonInterval) {
            $time = $this->freshTimestamp()->add($expireAfter);

            $expireAtColumn = $this->getExpireAtColumn();

            if (null !== $expireAtColumn && !$this->isDirty($expireAtColumn)) {
                $this->setExpireAt($time);
            }
        }

        return parent::updateTimestamps();
    }

    /**
     * Set the value of the "expire at" attribute.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function setExpireAt($value)
    {
        $this->{$this->getExpireAtColumn()} = $value;

        return $this;
    }

    /**
     * Get the expiry interval for the record.
     *
     * @return null|CarbonInterval
     */
    public function getExpireAfterInterval()
    {
        return null;
    }

    /**
     * Get the name of the "expire at" column.
     *
     * @return null|string
     */
    public function getExpireAtColumn()
    {
        return static::EXPIRE_AT;
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
            $this->insertAndSetId(
                $query,
                array_merge(
                    [
                        '__key__' => $this->getKey(),
                    ],
                    $attributes
                )
            );
        }

        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($attributes)) {
                return true;
            }

            $query->insert(
                array_merge(
                    [
                        '__key__' => $this->getKey(),
                    ],
                    $attributes
                ),
                $this->getQueryOptions()
            );
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

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (\count($dirty) > 0) {
            // First we need to create a fresh query instance and touch the creation and
            // update timestamp on the model which are maintained by us for developer
            // convenience. Then we will just continue saving the model instances.
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            $query->_upsert($this->getAttributes(), $this->getKey(), $this->getQueryOptions());

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Set the keys for a select query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSelectQuery($query)
    {
        $query->where('__key__', '=', $this->getKeyForSelectQuery());

        return $query;
    }

    /**
     * Get the primary key value for a select query.
     *
     * @return mixed
     */
    protected function getKeyForSelectQuery()
    {
        return $this->original['__key__'] ?? $this->getKey();
    }

    // {@inheritdoc}
    /* protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection);
    } */
}
