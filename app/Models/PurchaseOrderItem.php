<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'line_total',
        'notes',
    ];

    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors & Mutators for centavos to pesos conversion
    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // Scopes
    public function scopePartiallyReceived($query)
    {
        return $query->whereColumn('quantity_received', '<', 'quantity_ordered')
            ->where('quantity_received', '>', 0);
    }

    public function scopeFullyReceived($query)
    {
        return $query->whereColumn('quantity_received', '=', 'quantity_ordered');
    }

    public function scopeNotReceived($query)
    {
        return $query->where('quantity_received', 0);
    }
}
