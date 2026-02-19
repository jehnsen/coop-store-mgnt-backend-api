<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockByBranch extends Model
{
    protected $table = 'product_stock_by_branch';

    protected $fillable = [
        'product_id',
        'branch_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->whereColumn('product_stock_by_branch.quantity', '<=', 'products.reorder_point');
        });
    }
}
