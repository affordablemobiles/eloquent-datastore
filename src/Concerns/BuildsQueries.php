<?php

declare(strict_types=1);

namespace A1comms\EloquentDatastore\Concerns;

use Illuminate\Support\LazyCollection;

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
        $page   = 1;

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

    /**
     * Query lazily, by chunks of the given size.
     *
     * @param int $chunkSize
     *
     * @return \Illuminate\Support\LazyCollection
     *
     * @throws \InvalidArgumentException
     */
    public function lazy($chunkSize = 150)
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        return LazyCollection::make(function () use ($chunkSize) {
            $cursor = null;
            $page   = 1;

            while (true) {
                if (null !== $cursor) {
                    $this->start($cursor);
                }
                $results       = $this->limit($chunkSize)->get();
                $cursor        = $this->lastCursor();
                ++$page;

                foreach ($results as $result) {
                    yield $result;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }
            }
        });
    }

    public function chunkById($count, callable $callback, $column = null, $alias = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function eachById(callable $callback, $count = 1000, $column = null, $alias = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function lazyById($chunkSize = 1000, $column = null, $alias = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }

    public function lazyByIdDesc($chunkSize = 1000, $column = null, $alias = null)
    {
        throw new \LogicException('Not Implemented');

        return false;
    }
}
