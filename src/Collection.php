<?php

declare(strict_types=1);

namespace AffordableMobiles\EloquentDatastore;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class Collection extends BaseCollection
{
    /**
     * Has next any items.
     *
     * @return bool
     */
    public function hasNextItems()
    {
        return true;
        // return is_array($this->lastEvaluatedKey);
    }

    /**
     * Delete the whole collection.
     */
    public function delete()
    {
        $this->each(static function ($item): void {
            if (empty($item->__key__)) {
                throw new \LogicException('Unable to create query for collection with mixed types.');
            }
        });

        return DB::connection('datastore')->delete($this->pluck('__key__'));
    }
}
