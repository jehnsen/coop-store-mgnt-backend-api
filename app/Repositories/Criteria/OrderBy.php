<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class OrderBy implements Criteria
{
    protected string $column;
    protected string $direction;

    public function __construct(string $column, string $direction = 'asc')
    {
        $this->column = $column;
        $this->direction = $direction;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->orderBy($this->column, $this->direction);
    }
}
