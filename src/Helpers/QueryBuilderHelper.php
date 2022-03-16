<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Helpers;

use A1comms\EloquentDatastore\Client\DatastoreClient;
use A1comms\EloquentDatastore\Collection;
use Closure;
use Google\Cloud\Core\ExponentialBackoff;
use Google\Cloud\Datastore\Key;
use Google\Cloud\Datastore\Query\Query;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;

trait QueryBuilderHelper
{
    /**
     * Retrieve a single entity using key.
     *
     * @param mixed $id
     * @param mixed $columns
     */
    public function find($id, $columns = ['*'])
    {
        return $this->lookup($id, $columns);
    }

    /**
     * Retrieve a single entity using key.
     *
     * @param mixed $key
     * @param mixed $columns
     */
    public function lookup($key, $columns = ['*'])
    {
        return $this->onceWithColumns(Arr::wrap($columns), function () use ($key) {
            // Drop all columns if * is present.
            if (\in_array('*', $this->columns, true)) {
                $this->columns = [];
            }

            if (\is_array($key)) {
                $key = array_map(fn ($id) => $id instanceof Key ? $id : $this->getClient()->key($this->from, $id), $key);

                $result = (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'lookupBatch'], [$key]);

                $result = array_map(fn ($res) => $this->processor->processSingleResult($this, $res), $result['found']);

                return empty($this->columns) ? $result : array_map(fn ($res) => Arr::only($res, Arr::wrap($this->columns)), $result);
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

            if (false !== $this->startCursor) {
                $query->start($this->startCursor);
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

            $results         = (new ExponentialBackoff(6, [DatastoreClient::class, 'shouldRetry']))->execute([$this->getClient(), 'runQuery'], [$query]);
            $results         = $this->processor->processResults($this, $results);
            $this->endCursor = $results['cursor'];

            return $results['results'];
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
        return $this->lazy();
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->clone()->keysOnly()->take(1)->get('*')->count() > 0;
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
        return $this->clone()->keysOnly()->get($columns)->count();
    }

    public function selectSub($query, $as)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function selectRaw($expression, array $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function fromSub($query, $as)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function fromRaw($expression, $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function joinWhere($table, $first, $operator, $second, $type = 'inner')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function leftJoinWhere($table, $first, $operator, $second)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function leftJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function rightJoin($table, $first, $operator = null, $second = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function rightJoinWhere($table, $first, $operator, $second)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function rightJoinSub($query, $as, $first, $operator = null, $second = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function crossJoin($table, $first = null, $operator = null, $second = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function crossJoinSub($query, $as)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereRaw($sql, $bindings = [], $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereRaw($sql, $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereIn($column, $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNotIn($column, $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereIntegerInRaw($column, $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereIntegerNotInRaw($column, $values, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereIntegerNotInRaw($column, $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNull($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereBetweenColumns($column, array $values, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereBetween($column, iterable $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereBetweenColumns($column, array $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNotBetween($column, iterable $values, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNotBetweenColumns($column, array $values, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNotBetween($column, iterable $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNotBetweenColumns($column, array $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNotNull($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereDate($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereDate($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereTime($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereTime($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereDay($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereDay($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereMonth($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereMonth($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereYear($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereYear($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNested(Closure $callback, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function forNestedWhere()
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereExists(Closure $callback, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereExists(Closure $callback, $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereNotExists(Closure $callback, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereNotExists(Closure $callback)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function addWhereExistsQuery(BaseBuilder $query, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereRowValues($columns, $operator, $values, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereRowValues($columns, $operator, $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereJsonContains($column, $value, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereJsonContains($column, $value)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereJsonDoesntContain($column, $value, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereJsonDoesntContain($column, $value)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereJsonLength($column, $operator, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereJsonLength($column, $operator, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function dynamicWhere($method, $parameters)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function whereFullText($columns, $value, array $options = [], $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orWhereFullText($columns, $value, array $options = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function groupBy(...$groups)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function groupByRaw($sql, array $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orHaving($column, $operator = null, $value = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function havingNested(Closure $callback, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function addNestedHavingQuery($query, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function havingNull($columns, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orHavingNull($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function havingNotNull($columns, $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orHavingNotNull($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function havingBetween($column, array $values, $boolean = 'and', $not = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function havingRaw($sql, array $bindings = [], $boolean = 'and')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orHavingRaw($sql, array $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function inRandomOrder($seed = '')
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function orderByRaw($sql, $bindings = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function union($query, $all = false)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function unionAll($query)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function lock($value = true)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function lockForUpdate()
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function sharedLock()
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function toSql()
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function getCountForPagination($columns = ['*'])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function existsOr(Closure $callback)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function doesntExistOr(Closure $callback)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function min($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function max($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function sum($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function avg($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function average($column)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function aggregate($function, $columns = ['*'])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function numericAggregate($function, $columns = ['*'])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function insertOrIgnore(array $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function insertUsing(array $columns, $query)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function update(array $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function updateFrom(array $values)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function updateOrInsert(array $attributes, array $values = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function truncate()
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function raw($value)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    protected function createSub($query)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }
}
