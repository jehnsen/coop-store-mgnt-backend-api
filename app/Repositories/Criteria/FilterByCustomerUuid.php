<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterByCustomerUuid implements Criteria
{
    protected string $customerUuid;

    public function __construct(string $customerUuid)
    {
        $this->customerUuid = $customerUuid;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->whereHas('customer', function ($q) {
            $q->where('uuid', $this->customerUuid);
        });
    }
}
