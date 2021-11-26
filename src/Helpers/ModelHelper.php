<?php

namespace Appsero\LaravelDatastore\Helpers;

use LogicException;

trait ModelHelper
{
    public function delete(): bool
    {
        if (is_null($this->getKeyName())) {
            throw new LogicException('No primary key defined on model.');
        }

        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->newModelQuery()->getQuery()->delete($this->getKey());

        $this->exists = false;

        $this->fireModelEvent('deleted', false);

        return true;
    }
}
