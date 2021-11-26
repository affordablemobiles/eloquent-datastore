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
    public function lookup($id, $columns = [])
    {
        $key = $this->getClient()->key($this->from, $id);

        $result = $this->getClient()->lookup($key);

        $result = $this->processor->processSingleResult($this, $result);

        return empty($columns) ? $result : (object) Arr::only((array) $result, Arr::wrap($columns));
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

        if (count($this->wheres)) {
            foreach ($this->wheres as $filter) {
                if ($filter['type'] == 'Basic') {
                    $query->filter($filter['column'], $filter['operator'], $filter['value']);
                }
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

        $key = $key ? $this->getClient()->key($this->from, $key) : $this->getClient()->key($this->from);

        $entity = $this->getClient()->entity($key, $values, $options);

        return $this->getClient()->insert($entity);
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

        $key = $key ? $this->getClient()->key($this->from, $key) : $this->getClient()->key($this->from);

        $entity = $this->getClient()->entity($key, $values, $options);

        return $this->getClient()->upsert($entity);
    }
}
