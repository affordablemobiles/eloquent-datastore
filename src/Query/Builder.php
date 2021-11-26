<?php

namespace Appsero\LaravelDatastore\Query;

use Appsero\LaravelDatastore\Helpers\QueryBuilderHelper;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as BaseBuilder;

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
     * @inheritdoc
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar = null, Processor $processor = null)
    {
        $this->connection = $connection;
        //$this->expression = new Expression();
        $this->grammar = $grammar ?: $connection->getQueryGrammar();
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
     *
     * @return Builder
     */
    public function orderBy($column, $direction = 'ASCENDING'): Builder
    {
        $this->orders[] = [
            'column'    => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * set keys only.
     */
    public function keys(): Builder
    {
        $this->keysOnly = true;

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param
     *
     * @return Builder
     */
    public function orderByDesc($column): Builder
    {
        return $this->orderBy($column, 'DESCENDING');
    }

    /**
     * Enhanced pagination is not possible as count query is not possible in datastore.
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->simplePaginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Cursor Paginate.
     */
    public function cursorPaginate($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        return $this->paginateUsingCursor($perPage, $columns, $cursorName, $cursor);
    }
}
