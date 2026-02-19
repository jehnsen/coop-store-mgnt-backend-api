<?php

namespace App\Repositories\Criteria;

use App\Repositories\Contracts\BaseRepositoryInterface;

class LowStockProducts implements Criteria
{
    public function apply($query, BaseRepositoryInterface $repository)
    {
        return $query
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'reorder_point');
    }
}
