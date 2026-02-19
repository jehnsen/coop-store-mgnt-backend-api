<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class FilterBySupplierUuid implements Criteria
{
    protected string $supplierUuid;

    public function __construct(string $supplierUuid)
    {
        $this->supplierUuid = $supplierUuid;
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->whereHas('supplier', function ($q) {
            $q->where('uuid', $this->supplierUuid);
        });
    }
}
