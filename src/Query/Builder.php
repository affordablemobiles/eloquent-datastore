<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Query;

use A1comms\EloquentDatastore\Helpers\QueryBuilderHelper;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as BaseBuilder;
use InvalidArgumentException;

class Builder extends BaseBuilder
{
    use QueryBuilderHelper;

    public $keysOnly = false;

    /**
     * All the available clause operators.
     *
     * @var string[]
     */
    public $operators = [
        '=', '<', '>', '<=', '>=',
        'EQUAL', 'LESS_THAN', 'GREATER_THAN	', 'LESS_THAN_OR_EQUAL', 'GREATER_THAN_OR_EQUAL',
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar = null, Processor $processor = null)
    {
        $this->connection = $connection;
        // $this->expression = new Expression();
        $this->grammar   = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
    }

    /**
     * @return \Google\Cloud\Datastore\DatastoreClient
     */
    public function getClient()
    {
        return $this->getConnection()->getClient();
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param
     * @param string $direction
     * @param mixed  $column
     */
    public function orderBy($column, $direction = 'asc'): self
    {
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
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
     * Enhanced pagination is not possible as count query is not possible in datastore.
     *
     * @param mixed      $perPage
     * @param mixed      $columns
     * @param mixed      $pageName
     * @param null|mixed $page
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
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
