<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterByColumn implements Criteria
{
    protected string $column;
    protected mixed $value;
    protected string $operator;

    public function __construct(string $column, mixed $value, string $operator = '=')
    {
        $this->column = $column;
        $this->value = $value;
        $this->operator = $operator;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->where($this->column, $this->operator, $this->value);
    }
}
