<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Query;

use A1comms\EloquentDatastore\Collection;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process Datastore Results.
     *
     * @param $builder
     * @param $results
     * @param null|mixed $excludeKey
     *
     * @return mixed
     */
    public function processResults($builder, $results, $excludeKey = null)
    {
        $entities       = [];
        $nextPageCursor = null;

        foreach ($results as $result) {
            $entities[]     = $this->processSingleResult($builder, $result, $excludeKey);
            $nextPageCursor = $result->cursor();
        }

        $entities = array_filter($entities);

        return [
            'results' => new Collection($entities),
            'cursor'  => $nextPageCursor,
        ];
    }

    /**
     * Process single entity result.
     *
     * @param mixed      $builder
     * @param mixed      $result
     * @param null|mixed $excludeKey
     */
    public function processSingleResult($builder, $result, $excludeKey = null): array
    {
        $entity = $result->get();

        if ($excludeKey instanceof Key && $result->key() == $excludeKey) {
            return [];
        }

        $entity['id'] ??= $result->key()->path()[0]['name'] ?? $result->key()->path()[0]['id'];
        $entity['_key']    = $result->key()->path()[0];
        $entity['_keys']   = $result->key()->path();
        $entity['__key__'] = $result->key();

        return $entity;
    }
}
