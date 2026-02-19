<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

interface Criteria
{
    /**
     * Apply criteria to query
     *
     * @param mixed $query - Eloquent Builder
     * @param BaseRepositoryInterface $repository
     * @return mixed
     */
    public function apply($query, BaseRepositoryInterface $repository);
}
