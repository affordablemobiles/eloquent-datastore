<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Query;

use A1comms\EloquentDatastore\Collection;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process Datastore Results.
     *
     * @param $builder
     * @param $results
     *
     * @return mixed
     */
    public function processResults($builder, $results)
    {
        $entities       = [];
        $nextPageCursor = null;

        foreach ($results as $result) {
            $entities[]     = $this->processSingleResult($builder, $result);
            $nextPageCursor = $result->cursor();
        }

        return new Collection($entities);
    }

    /**
     * Process single entity result.
     *
     * @param mixed $builder
     * @param mixed $result
     */
    public function processSingleResult($builder, $result): array
    {
        $entity = $result->get();
        $entity['id'] ??= $result->key()->path()[0]['name'] ?? $result->key()->path()[0]['id'];
        $entity['_key']    = $result->key()->path()[0];
        $entity['_keys']   = $result->key()->path();
        $entity['__key__'] = $result->key();

        return $entity;
    }
}
