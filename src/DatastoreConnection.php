<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore;

use AffordableMobiles\EloquentDatastore\Client\DatastoreClient;
use AffordableMobiles\EloquentDatastore\Query\Builder;
use AffordableMobiles\EloquentDatastore\Query\Grammar as QueryGrammar;
use AffordableMobiles\EloquentDatastore\Query\Processor;
use AffordableMobiles\EloquentDatastore\Query\RawExpression;
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
     * @param mixed $name
     * @param mixed $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $this->forwardCallTo($this->client, $name, $arguments);
    }

    /**
     * Make datastore client.
     *
     * @param mixed $config
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
     * @param mixed $table
     */
    public function from($table): Builder
    {
        return $this->query()->from($table);
    }

    /**
     * Set the query grammar to the default implementation.
     */
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
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
     * @param mixed $client
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
     * @param mixed $kind
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

    public function disconnect(): void
    {
        $this->setClient(null);
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar($this);
    }
}
