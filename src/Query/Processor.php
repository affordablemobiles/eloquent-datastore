<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Query;

use AffordableMobiles\EloquentDatastore\Collection;
use Google\Cloud\Datastore\Key;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    /**
     * Process Datastore Results.
     *
     * @param null|mixed $excludeKey
     * @param mixed      $builder
     * @param mixed      $results
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
        $entity['_key']       = $result->key()->path()[0];
        $entity['_keys']      = $result->key()->path();
        $entity['__key__']    = $result->key();
        $entity['__parent__'] = self::getParentKey($result->key());

        return $entity;
    }

    public static function getParentKey(Key $sourceKey): ?Key
    {
        $path = $sourceKey->path();

        // A key with 1 or 0 elements in its path has no parent.
        if (\count($path) <= 1) {
            return null;
        }

        // Get the path for the parent (all elements except the last one).
        $parentPath = \array_slice($path, 0, -1);

        // We need to get the other properties (project, namespace, database)
        // from the source key to construct a new, valid key.
        $keyData   = $sourceKey->jsonSerialize();
        $partition = $keyData['partitionId'];

        // Get the project ID (required for the constructor)
        $projectId = $partition['projectId'];

        // Build the options array for the new key
        $options = [
            'path'        => $parentPath,
            'namespaceId' => $partition['namespaceId'] ?? null,
            'databaseId'  => $partition['databaseId']  ?? null,
        ];

        // Create the new parent Key object.
        return new Key($projectId, $options);
    }
}
