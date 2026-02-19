<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class SearchMultipleColumns implements Criteria
{
    protected string $searchTerm;
    protected array $columns;

    public function __construct(string $searchTerm, array $columns)
    {
        $this->searchTerm = $searchTerm;
        $this->columns = $columns;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->where(function ($q) {
            foreach ($this->columns as $column) {
                $q->orWhere($column, 'LIKE', "%{$this->searchTerm}%");
            }
        });
    }
}
