<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatronageRefundAllocation extends Model
{
    use HasUuid, BelongsToStore;

    // NO SoftDeletes — immutable audit trail once computed

    protected $fillable = [
        'uuid',
        'store_id',
        'batch_id',
        'customer_id',
        'member_purchases',
        'allocation_percentage',
        'allocation_amount',
        'status',
        'payment_method',
        'reference_number',
        'paid_date',
        'paid_by',
        'notes',
    ];

    protected $casts = [
        'paid_date'            => 'date',
        'allocation_percentage' => 'decimal:6',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function memberPurchases(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function allocationAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PatronageRefundBatch::class, 'batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForfeited($query)
    {
        return $query->where('status', 'forfeited');
    }
}
