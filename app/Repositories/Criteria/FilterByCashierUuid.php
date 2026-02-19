<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterByCashierUuid implements Criteria
{
    protected string $cashierUuid;

    public function __construct(string $cashierUuid)
    {
        $this->cashierUuid = $cashierUuid;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('uuid', $this->cashierUuid);
        });
    }
}
