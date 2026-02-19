<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class ActiveOnly implements Criteria
{
    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->where('is_active', true);
    }
}
