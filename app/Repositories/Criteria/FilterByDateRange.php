<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Carbon\Carbon;

class FilterByDateRange implements Criteria
{
    protected string $column;
    protected Carbon $startDate;
    protected Carbon $endDate;

    public function __construct(string $column, Carbon $startDate, Carbon $endDate)
    {
        $this->column = $column;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->whereBetween($this->column, [$this->startDate, $this->endDate]);
    }
}
