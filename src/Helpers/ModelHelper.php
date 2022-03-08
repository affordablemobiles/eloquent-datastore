<?php

declare(strict_types=1);

namespace Appsero\LaravelDatastore\Helpers;

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
}
