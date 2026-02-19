<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'supplier_price',
        'lead_time_days',
        'minimum_order_quantity',
        'is_preferred',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_preferred' => 'boolean',
        ];
    }

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors & Mutators for centavos to pesos conversion
    protected function supplierPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // Scopes
    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }
}
