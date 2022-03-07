<?php

namespace Appsero\LaravelDatastore;

use Appsero\LaravelDatastore\Query\Builder;
use Appsero\LaravelDatastore\Query\Grammar;
use Appsero\LaravelDatastore\Query\Processor;
use Appsero\LaravelDatastore\Query\RawExpression;
use Appsero\LaravelDatastore\Client\DatastoreClient;
use Illuminate\Database\Connection;
use Illuminate\Support\Traits\ForwardsCalls;

class DatastoreConnection extends Connection
{
    use ForwardsCalls;

    /**
     * Datastore Client.
     *
     * @var DatastoreClient
     */
    protected $client;

    /**
     * @var string
     */
    public $tablePrefix;

    /**
     * Query grammar.
     *
     * @var Grammar
     */
    public $queryGrammar;

    /**
     * Result processor.
     *
     * @var Processor
     */
    public $postProcessor;

    public function __construct($config)
    {
        parent::__construct(null);

        $this->config = $config;
        $this->makeClient($config);

        $this->tablePrefix = $config['prefix'] ?? null;

        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Make datastore client.
     *
     * @param $config
     *
     * @return DatastoreConnection
     */
    public function makeClient($config): DatastoreConnection
    {
        $client = new DatastoreClient([
            'transport' => $config['transport'] ?? 'grpc',
        ]);

        return $this->setClient($client);
    }

    /**
     * Query builder.
     *
     * @return Builder
     */
    public function query(): Builder
    {
        return new Builder($this);
    }

    /**
     * Set the table.
     *
     * @param $table
     *
     * @return Builder
     */
    public function from($table): Builder
    {
        return $this->query()->from($table);
    }

    /**
     * Get default query grammar.
     */
    public function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new Grammar());
    }

    /**
     * Get default post processor.
     *
     * @return Processor
     */
    public function getDefaultPostProcessor(): Processor
    {
        return new Processor();
    }

    /**
     * Get the datastore client.
     *
     * @return DatastoreClient
     */
    public function getClient(): DatastoreClient
    {
        return $this->client;
    }

    /**
     * Set the datastore client.
     *
     * @param $client
     *
     * @return $this
     */
    public function setClient($client): DatastoreConnection
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Call datastore client methods.
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->forwardCallTo($this->client, $name, $arguments);
    }

    /**
     * Set the table/kind name.
     *
     * @param string $table
     * @param null   $as
     *
     * @return Builder
     */
    public function table($table, $as = null): Builder
    {
        return $this->from($table);
    }

    /**
     * Set the table/kind name.
     *
     * @param $kind
     *
     * @return Builder
     */
    public function kind($kind): Builder
    {
        return $this->from($kind);
    }

    /**
     * @param mixed $value
     *
     * @return RawExpression
     */
    public function raw($value): RawExpression
    {
        return new RawExpression($value);
    }

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->setClient(null);
    }
}
