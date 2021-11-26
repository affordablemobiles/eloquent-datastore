<?php

namespace Appsero\LaravelDatastore;

use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use LogicException;

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
        //return is_array($this->lastEvaluatedKey);
    }

    /**
     * Delete the whole collection.
     */
    public function delete()
    {
        $this->each(function ($item) {
            if (empty($item->_KEY_)) {
                throw new LogicException('Unable to create query for collection with mixed types.');
            }
        });

        return DB::connection('datastore')->delete($this->pluck('_KEY_'));
    }
}
