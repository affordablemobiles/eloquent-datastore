<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Helpers;

use A1comms\EloquentDatastore\Client\DatastoreClient;
use A1comms\EloquentDatastore\Collection;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Query\Query;
use Illuminate\Support\Arr;

trait QueryBuilderHelper
{
    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = ['*'])
    {
        return $this->lookup($id, $columns);
    }

    /**
     * Retrieve a single entity using key.
     *
     * @param mixed $columns
     */
    public function lookup(Key $key, $columns = ['*'])
    {
        return $this->onceWithColumns(Arr::wrap($columns), function () use ($key, $columns) {
            if (!empty($columns)) {
                $this->addSelect($columns);
            }

            // Drop all columns if * is present.
            if (\in_array('*', $this->columns, true)) {
                $this->columns = [];
            }

            $result = (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'lookup'], [$key]);

            if (!$result || empty($result)) {
                return null;
            }

            $result = $this->processor->processSingleResult($this, $result);

            return empty($this->columns) ? $result : Arr::only($result, Arr::wrap($this->columns));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        return $this->onceWithColumns(Arr::wrap($columns), function () {
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
                    } else {
                        throw new \LogicException('Filter type "'.$filter['type'].'" not implemented');
                    }
                }
            }

            if (\is_array($this->orders) && \count($this->orders)) {
                foreach ($this->orders as $order) {
                    $direction = 'DESC' === strtoupper($order['direction']) ? Query::ORDER_DESCENDING : Query::ORDER_ASCENDING;
                    $query->order($order['column'], $direction);
                }
            }

            $results = (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'runQuery'], [$query]);

            return $this->processor->processResults($this, $results);
        });
    }

    /**
     * Get a collection instance containing the values of a given column.
     *
     * @param string      $column
     * @param null|string $key
     *
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $queryResult = $this->get([$column]);

        if (empty($queryResult)) {
            return collect();
        }
        if ($queryResult instanceof Collection && $queryResult->isEmpty()) {
            return collect();
        }

        return \is_array($queryResult[0])
                    ? $this->pluckFromArrayColumn($queryResult, $column, $key)
                    : $this->pluckFromObjectColumn($queryResult, $column, $key);
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

        return (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'deleteBatch'], [$keys]);
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

        return false !== (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'insertBatch'], [$entities]);
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

        return (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'insert'], [$entity])->pathEndIdentifier();
    }

    public function _upsert(array $values, $keys, $options = [])
    {
        if (empty($this->from)) {
            throw new \LogicException('No kind/table specified');
        }

        if (empty($values)) {
            return true;
        }

        if (!\is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (!\is_array(reset($keys))) {
            $keys = [$keys];
        }
        if (\count($keys) !== \count($values)) {
            throw new LogicException('key count mismatch');
        }

        $entities = [];

        foreach ($values as $index => $value) {
            if (isset($value['id'])) {
                unset($value['id']);
            }

            $key = $keys[$index];

            if ($key instanceof Key) {
                $entities[] = $this->getClient()->entity($key, $value, $options);
            } else {
                throw new \LogicException('invalid key');
            }
        }

        return (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'upsertBatch'], [$entities]);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param null|int                                  $perPage
     * @param array                                     $columns
     * @param string                                    $cursorName
     * @param null|\Illuminate\Pagination\Cursor|string $cursor
     *
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        throw new \LogicException('NEED TO IMPLEMENT');
    }

    /**
     * Get a lazy collection for the given query.
     *
     * @return \Illuminate\Support\LazyCollection
     */
    public function cursor()
    {
        throw new \LogicException('NEED TO IMPLEMENT');
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        throw new \LogicException('NEED TO IMPLEMENT');
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param string $columns
     *
     * @return int
     */
    public function count($columns = '*')
    {
        throw new \LogicException('NEED TO IMPLEMENT');
    }

    public function selectSub($query, $as): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function selectRaw($expression, array $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function fromSub($query, $as): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function fromRaw($expression, $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function joinWhere($table, $first, $operator, $second, $type = 'inner'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function leftJoin($table, $first, $operator = null, $second = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function leftJoinWhere($table, $first, $operator, $second): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function leftJoinSub($query, $as, $first, $operator = null, $second = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function rightJoin($table, $first, $operator = null, $second = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function rightJoinWhere($table, $first, $operator, $second): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function rightJoinSub($query, $as, $first, $operator = null, $second = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function crossJoin($table, $first = null, $operator = null, $second = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function crossJoinSub($query, $as): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereRaw($sql, $bindings = [], $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereRaw($sql, $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereIn($column, $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNotIn($column, $values, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNotIn($column, $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereIntegerInRaw($column, $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereIntegerNotInRaw($column, $values, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereIntegerNotInRaw($column, $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNull($columns, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNull($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNotNull($columns, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereBetween($column, iterable $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereBetweenColumns($column, array $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNotBetween($column, iterable $values, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNotBetweenColumns($column, array $values, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNotBetween($column, iterable $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNotBetweenColumns($column, array $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNotNull($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereDate($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereDate($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereTime($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereTime($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereDay($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereDay($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereMonth($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereMonth($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereYear($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereYear($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNested(Closure $callback, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function forNestedWhere(): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function addNestedWhereQuery($query, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereExists(Closure $callback, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereExists(Closure $callback, $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereNotExists(Closure $callback, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereNotExists(Closure $callback): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function addWhereExistsQuery(self $query, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereRowValues($columns, $operator, $values, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereRowValues($columns, $operator, $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereJsonContains($column, $value, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereJsonContains($column, $value): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereJsonDoesntContain($column, $value, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereJsonDoesntContain($column, $value): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereJsonLength($column, $operator, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function dynamicWhere($method, $parameters): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function whereFullText($columns, $value, array $options = [], $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orWhereFullText($columns, $value, array $options = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function groupBy(...$groups): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function groupByRaw($sql, array $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function having($column, $operator = null, $value = null, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orHaving($column, $operator = null, $value = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function havingNested(Closure $callback, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function addNestedHavingQuery($query, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function havingNull($columns, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orHavingNull($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function havingNotNull($columns, $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orHavingNotNull($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function havingBetween($column, array $values, $boolean = 'and', $not = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function havingRaw($sql, array $bindings = [], $boolean = 'and'): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orHavingRaw($sql, array $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function inRandomOrder($seed = ''): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function orderByRaw($sql, $bindings = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function union($query, $all = false): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function unionAll($query): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function lock($value = true): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function lockForUpdate(): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function sharedLock(): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function toSql(): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function getCountForPagination($columns = ['*']): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function existsOr(Closure $callback): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function doesntExistOr(Closure $callback): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function min($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function max($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function sum($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function avg($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function average($column): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function aggregate($function, $columns = ['*']): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function numericAggregate($function, $columns = ['*']): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function insertOrIgnore(array $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function insertUsing(array $columns, $query): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function update(array $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function updateFrom(array $values): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function updateOrInsert(array $attributes, array $values = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function upsert(array $values, $uniqueBy, $update = null): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function increment($column, $amount = 1, array $extra = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function decrement($column, $amount = 1, array $extra = []): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function truncate(): void
    {
        throw new \LogicException('Not Implemented');
    }

    public function raw($value): void
    {
        throw new \LogicException('Not Implemented');
    }

    protected function createSub($query): void
    {
        throw new \LogicException('Not Implemented');
    }
}
