<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class WithRelations implements Criteria
{
    protected array $relations;

    public function __construct(array|string $relations)
    {
        $this->relations = is_array($relations) ? $relations : [$relations];
    }

    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query->with($this->relations);
    }
}
