<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Helpers;

use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Query\Query;
use Illuminate\Support\Arr;

trait QueryBuilderHelper
{
    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = [])
    {
        return $this->lookup($id, $columns);
    }

    /**
     * Retrieve a single entity using key.
     *
     * @param mixed $columns
     */
    public function lookup(Key $key, $columns = [])
    {
        if (\in_array('*', $columns, true)) {
            $columns = [];
        }

        $result = $this->getClient()->lookup($key);

        if (!$result || empty($result)) {
            return null;
        }

        $result = $this->processor->processSingleResult($this, $result);

        return empty($columns) ? $result : Arr::only($result, Arr::wrap($columns));
    }

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        if (!empty($columns)) {
            $this->addSelect($columns);
        }

        // Drop all columns if * is present.
        if (\in_array('*', $this->columns, true)) {
            $this->columns = [];
        }

        $query = $this->getClient()->query()->kind($this->from)
            ->projection($this->columns)
            ->offset($this->offset)
            ->limit($this->limit)
        ;

        if ($this->keysOnly) {
            $query->keysOnly();
        }

        if (true === $this->distinct) {
            throw new \LogicException('must specify columns for distinct query');
        }
        if (\is_array($this->distinct)) {
            $query->distinctOn($this->distinct);
        }

        if (\is_array($this->wheres) && \count($this->wheres)) {
            foreach ($this->wheres as $filter) {
                if ('Basic' === $filter['type']) {
                    $query->filter($filter['column'], $filter['operator'], $filter['value']);
                }
            }
        }

        if (\is_array($this->orders) && \count($this->orders)) {
            foreach ($this->orders as $order) {
                $direction = 'DESC' === strtoupper($order['direction']) ? Query::ORDER_DESCENDING : Query::ORDER_ASCENDING;
                $query->order($order['column'], $direction);
            }
        }

        $results = $this->getClient()->runQuery($query);

        return $this->processor->processResults($this, $results);
    }

    /**
     * Key Only Query.
     */
    public function getKeys()
    {
        return $this->keys()->get()->pluck('__key__');
    }

    /**
     * Key Only Query.
     */
    public function keysOnly()
    {
        return $this->keys();
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key = null)
    {
        if (null === $key) {
            $keys = $this->keys()->get()->pluck('__key__')->toArray();
        } else {
            if ($key instanceof Key || (\is_array($key) && $key[0] instanceof Key) || empty($this->from)) {
                $keys = Arr::wrap($key);
            } else {
                if (\is_array($key)) {
                    $keys = array_map(fn ($item) => $item instanceof Key ? $item : $this->getClient()->key($this->from, $item), $key);
                } else {
                    $keys = [$this->getClient()->key($this->from, $key)];
                }

                return $keys;
            }
        }

        return $this->getClient()->deleteBatch($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $values, $options = [])
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!\is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        $entities = [];

        foreach ($values as $key => $value) {
            if (isset($value['id'])) {
                $key = $this->getClient()->key($this->from, $value['id'], [
                    'identifierType' => Key::TYPE_NAME,
                ]);
                unset($value['id']);
            } else {
                $key = $this->getClient()->key($this->from);
            }

            $entities[] = $this->getClient()->entity($key, $value, $options);
        }

        return false !== $this->getClient()->insertBatch($entities);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param null|string $sequence
     * @param mixed       $options
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null, $options = []): string
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        if (isset($values['id'])) {
            throw new \LogicException('insertGetId with key set');
        }

        $key = $this->getClient()->key($this->from);

        $entity = $this->getClient()->entity($key, $values, $options);

        return $this->getClient()->insert($entity)->pathEndIdentifier();
    }

    /**
     * {@inheritdoc}
     */
    public function upsert(array $values, $key = '', $options = [])
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        if (empty($values)) {
            return true;
        }

        if (isset($values['id'])) {
            unset($values['id']);
        }

        if ($key instanceof Key) {
            $entity = $this->getClient()->entity($key, $values, $options);

            return $this->getClient()->upsert($entity)->pathEndIdentifier();
        }

        throw new \LogicException('invalid key');
    }
}
