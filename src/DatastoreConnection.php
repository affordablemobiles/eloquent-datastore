<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore;

use A1comms\EloquentDatastore\Client\DatastoreClient;
use A1comms\EloquentDatastore\Query\Builder;
use A1comms\EloquentDatastore\Query\Grammar;
use A1comms\EloquentDatastore\Query\Processor;
use A1comms\EloquentDatastore\Query\RawExpression;
use Illuminate\Database\Connection;
use Illuminate\Support\Traits\ForwardsCalls;

class DatastoreConnection extends Connection
{
    use ForwardsCalls;

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

    /**
     * Datastore Client.
     *
     * @var DatastoreClient
     */
    protected $client;

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
     * Call datastore client methods.
     *
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments): void
    {
        $this->forwardCallTo($this->client, $name, $arguments);
    }

    /**
     * Make datastore client.
     *
     * @param $config
     */
    public function makeClient($config): self
    {
        $client = new DatastoreClient([
            'transport' => $config['transport'] ?? 'grpc',
        ]);

        return $this->setClient($client);
    }

    /**
     * Query builder.
     */
    public function query(): Builder
    {
        return new Builder($this);
    }

    /**
     * Set the table.
     *
     * @param $table
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
     */
    public function getDefaultPostProcessor(): Processor
    {
        return new Processor();
    }

    /**
     * Get the datastore client.
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
    public function setClient($client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the table/kind name.
     *
     * @param string $table
     * @param null   $as
     */
    public function table($table, $as = null): Builder
    {
        return $this->from($table);
    }

    /**
     * Set the table/kind name.
     *
     * @param $kind
     */
    public function kind($kind): Builder
    {
        return $this->from($kind);
    }

    /**
     * @param mixed $value
     */
    public function raw($value): RawExpression
    {
        return new RawExpression($value);
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->setClient(null);
    }
}
