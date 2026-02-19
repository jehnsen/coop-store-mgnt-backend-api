<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class SearchByColumn implements Criteria
{
    protected string $column;
    protected string $search;

    public function __construct(string $column, string $search)
    {
        $this->column = $column;
        $this->search = $search;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->where($this->column, 'LIKE', '%' . $this->search . '%');
    }
}
