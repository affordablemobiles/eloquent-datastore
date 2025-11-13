<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests;

use AffordableMobiles\EloquentDatastore\Client\DatastoreClient;
use Google\Cloud\Datastore\EntityInterface;
use Google\Cloud\Datastore\V1\PropertyMask;

class TestDatastoreClient extends DatastoreClient
{
    /**
     * Get multiple entities.
     * Overridden to manually apply propertyMask AND shuffle results
     * to simulate production behavior in the emulator.
     */
    public function lookupBatch(array $keys, array $options = []): array
    {
        // 1. Get the "real" (buggy) result from the parent
        $result = parent::lookupBatch($keys, $options);

        // 2. Check if we are in the emulator
        $isEmulator = (bool) getenv('DATASTORE_EMULATOR_HOST');
        if (!$isEmulator) {
            return $result; // In production, do nothing.
        }

        // 3. Apply the propertyMask shim (if needed)
        $hasMask = isset($options['propertyMask']);
        if ($hasMask && !empty($result['found'])) {
            $filteredFound = [];
            foreach ($result['found'] as $entity) {
                $filteredFound[] = $this->applyEmulatorMask($entity, $options['propertyMask']);
            }
            $result['found'] = $filteredFound;
        }

        // 4. Shuffle the results to simulate production
        // This forces tests to be order-agnostic.
        if (!empty($result['found'])) {
            shuffle($result['found']);
        }

        // 5. Return the corrected and shuffled result
        return $result;
    }

    /**
     * Manually filters an entity's properties to simulate
     * propertyMask behavior for the buggy emulator.
     */
    private function applyEmulatorMask(EntityInterface $entity, PropertyMask $mask): EntityInterface
    {
        // Convert RepeatedField to an array
        $paths = iterator_to_array($mask->getPaths());

        // Case 1: keysOnly projection ('__key__')
        if (1 === \count($paths) && '__key__' === $paths[0]) {
            $class = \get_class($entity);

            return new $class($entity->key(), [], [
                'excludeFromIndexes' => $entity->excludeFromIndexes(),
            ]);
        }

        // Case 2: Standard projection
        $data = $entity->get();
        foreach (array_keys($data) as $property) {
            // Use the $paths array
            if (!\in_array($property, $paths, true)) {
                unset($entity[$property]);
            }
        }

        return $entity;
    }
}
