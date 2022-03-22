<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Helpers;

use LogicException;

trait ModelHelper
{
    public function delete(): bool
    {
        if (null === $this->getKeyName()) {
            throw new LogicException('No primary key defined on model.');
        }

        if (!$this->exists) {
            return false;
        }

        if (false === $this->fireModelEvent('deleting')) {
            return false;
        }

        $this->newModelQuery()->getQuery()->delete($this->getKey());

        $this->exists = false;

        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param array|\Illuminate\Support\Collection|int|string $ids
     *
     * @return int
     */
    public static function destroy($ids)
    {
        if ($ids instanceof EloquentCollection) {
            $ids = $ids->modelKeys();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = \is_array($ids) ? $ids : \func_get_args();

        if (0 === \count($ids)) {
            return 0;
        }

        $records = $this->newModelQuery()->lookup($ids, ['__key__']);

        $records->delete();

        return \count($ids);
    }
}
