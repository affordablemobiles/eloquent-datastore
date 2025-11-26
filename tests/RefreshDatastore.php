<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore\Tests;

use AffordableMobiles\EloquentDatastore\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait RefreshDatastore
{
    public function refreshDatastore(): void
    {
        // Check if the test class has defined the kinds to clear.
        if (!property_exists($this, 'kindsToClear') || empty($this->kindsToClear)) {
            return;
        }

        $client = DB::connection('datastore')->getClient();

        // Loop over the kinds defined in the specific test class.
        foreach ($this->kindsToClear as $kind) {
            $kindName = $kind;

            // Check if it's a model class string (e.g., User::class)
            if (class_exists($kind) && is_subclass_of($kind, Model::class)) {
                // Instantiate the model to get its table (Kind) name
                $kindName = (new $kind())->getTable();
            }

            $query = $client->query()
                ->kind($kindName)
                ->keysOnly()
            ;

            $results = $client->runQuery($query);
            $keys    = [];
            foreach ($results as $result) {
                $keys[] = $result->key();
            }

            if (!empty($keys)) {
                $client->deleteBatch($keys);
            }
        }
    }
}
