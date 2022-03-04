<?php

namespace Appsero\LaravelDatastore\Helpers;

use Google\Cloud\Datastore\Key;
use Illuminate\Support\Arr;

trait QueryBuilderHelper
{
    /**
     * @inheritdoc
     */
    public function find($id, $columns = [])
    {
        return $this->lookup($id, $columns);
    }

    /**
     * Retrieve a single entity using key.
     */
    public function lookup(Key $key, $columns = [])
    {
        dd($key);
        $result = $this->getClient()->lookup($key);

        if (! $result || empty($result)) {
            return null;
        }

        $result = $this->processor->processSingleResult($this, $result);

        return empty($columns) ? $result : Arr::only($result, Arr::wrap($columns));
    }

    /**
     * @inheritdoc
     */
    public function get($columns = ['*'])
    {
        if (!empty($columns)) {
            $this->addSelect($columns);
        }

        // Drop all columns if * is present.
        if (in_array('*', $this->columns)) {
            $this->columns = [];
        }

        $query = $this->getClient()->query()->kind($this->from)
            ->projection($this->columns)
            ->offset($this->offset)
            ->limit($this->limit);

        if ($this->keysOnly) {
            $query->keysOnly();
        }

        if (is_array($this->wheres) && count($this->wheres)) {
            foreach ($this->wheres as $filter) {
                if ($filter['type'] == 'Basic') {
                    $query->filter($filter['column'], $filter['operator'], $filter['value']);
                }
            }
        }

        if (is_array($this->orders) && count($this->orders)) {
            foreach ($this->orders as $order) {
                $query->order($order['column'], $order['direction']);
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
        return $this->keys()->get()->pluck('_keys');
    }

    /**
     * Key Only Query.
     */
    public function keysOnly()
    {
        return $this->getKeys();
    }

    /**
     * @inheritdoc
     */
    public function delete($key = null)
    {
        if (is_null($key)) {
            $keys = $this->keys()->get()->pluck('__key__')->toArray();
        } else {
            if ($key instanceof Key || (is_array($key) && $key[0] instanceof Key) || empty($this->from)) {
                $keys = Arr::wrap($key);
            } else {
                if (is_array($key)) {
                    $keys = array_map(function ($item) {
                        return $item instanceof Key ? $item : $this->getClient()->key($this->from, $item);
                    }, $key);
                } else {
                    $keys = [$this->getClient()->key($this->from, $key)];
                }

                return $keys;
            }
        }

        return $this->getClient()->deleteBatch($keys);
    }

    /**
     * @inheritdoc
     */
    public function insert(array $values, $key = '', $options = [])
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        if (empty($values)) {
            return true;
        }

        if (isset($values['id'])) {
            $key = $this->getClient()->key($this->from, $values['id'], [
                'identifierType' => Key::TYPE_NAME
            ]);
            unset($values['id']);
        } else {
            throw new \LogicException('insert without key specified');
        }

        $entity = $this->getClient()->entity($key, $values, $options);

        return $this->getClient()->insert($entity);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null): string
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        if (isset($values['id'])) {
            throw new \LogicException('insertGetId with key set');
        }

        $key = $this->getClient()->key($this->from);

        $entity = $this->getClient()->entity($key, $values, []);

        return dd(['insertId' => $this->getClient()->insert($entity)]);
    }

    /**
     * @inheritdoc
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

            return $this->getClient()->upsert($entity);
        } else {
            throw new \LogicException('invalid key');
        }
    }
}
