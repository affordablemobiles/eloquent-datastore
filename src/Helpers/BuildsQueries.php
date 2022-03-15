<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Helpers;

trait BuildsQueries
{
    /**
     * Chunk the results of the query.
     *
     * @param int $count
     */
    public function chunk($count, callable $callback): bool
    {
        $cursor = null;

        $page = 1;

        do {
            if (null !== $cursor) {
                $this->start($cursor);
            }

            $results       = $this->limit($count)->get();
            $cursor        = $this->lastCursor();
            $countResults  = $results->count();

            if (0 === $countResults) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if (false === $callback($results, $page)) {
                return false;
            }

            unset($results);

            ++$page;
        } while ($countResults === $count);

        return true;
    }
}
