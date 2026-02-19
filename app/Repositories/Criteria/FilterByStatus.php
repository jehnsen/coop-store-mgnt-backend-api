<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterByStatus implements Criteria
{
    protected string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->where('status', $this->status);
    }
}
