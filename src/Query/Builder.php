<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Query;

use AffordableMobiles\EloquentDatastore\Concerns\BuildsQueries;
use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Facades\DB;
use Rennokki\QueryCache\Contracts\QueryCacheModuleInterface;

class Builder extends BaseBuilder implements QueryCacheModuleInterface
{
    use BuildsQueries;
    use Concerns\QueriesDatastore;
    use Concerns\QueryCacheModule;

    public $startCursor = false;
    public $endCursor   = false;

    /**
     * All the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '=', '<', '>', '<=', '>=',
        'EQUAL', 'LESS_THAN', 'GREATER_THAN	', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN_OR_EQUAL',
    ];
    protected $keysOnly = false;
    protected $ancestor = false;
    protected $namespaceId;

    public function __construct(ConnectionInterface $connection, ?Grammar $grammar = null, ?Processor $processor = null)
    {
        $this->connection = $connection;
        // $this->expression = new Expression();
        $this->grammar   = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
    }

    /**
     * @return DatastoreClient
     */
    public function getClient()
    {
        return DB::connection('datastore')->getClient();
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $direction
     * @param mixed  $column
     */
    public function orderBy($column, $direction = 'asc'): self
    {
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->orders[] = [
            'column'    => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * set keys only.
     */
    public function keys(): self
    {
        $this->keysOnly = true;

        return $this;
    }

    /**
     * set ancestor key.
     */
    public function hasAncestor(Key $key): self
    {
        $this->ancestor = $key;

        return $this;
    }

    /**
     * set the namespace.
     *
     * @param mixed $namespaceId
     */
    public function namespace($namespaceId): self
    {
        $this->namespaceId = $namespaceId;

        return $this;
    }

    /**
     * Set the start cursor for the query.
     *
     * @param mixed $cursor
     */
    public function start($cursor): self
    {
        $this->startCursor = $cursor;

        return $this;
    }

    /**
     * Return the end cursor from the last query.
     */
    public function lastCursor()
    {
        return $this->endCursor;
    }

    /**
     * Enhanced pagination is not possible as count query is not possible in datastore.
     *
     * @param mixed      $perPage
     * @param mixed      $columns
     * @param mixed      $pageName
     * @param null|mixed $page
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return $this->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Cursor Paginate.
     *
     * @param mixed      $perPage
     * @param mixed      $columns
     * @param mixed      $cursorName
     * @param null|mixed $cursor
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        return $this->paginateUsingCursor($perPage, $columns, $cursorName, $cursor);
    }
}
