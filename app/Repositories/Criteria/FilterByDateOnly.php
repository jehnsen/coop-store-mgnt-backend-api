<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterByDateOnly implements Criteria
{
    protected string $column;
    protected string $date;
    protected string $operator;

    public function __construct(string $column, string $date, string $operator = '>=')
    {
        $this->column = $column;
        $this->date = $date;
        $this->operator = $operator;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->whereDate($this->column, $this->operator, $this->date);
    }
}
