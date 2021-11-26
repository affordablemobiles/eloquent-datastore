<?php

namespace Appsero\LaravelDatastore\Query;

class RawExpression
{
    /**
     * @var array
     */
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * Get raw query.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->query;
    }
}
