<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'supplier_id',
        'branch_id',
        'user_id',
        'po_number',
        'status',
        'payment_status',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'payment_due_date',
        'payment_completed_date',
        'total_amount',
        'amount_paid',
        'notes',
        'terms_and_conditions',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'received_date' => 'date',
            'payment_due_date' => 'date',
            'payment_completed_date' => 'date',
        ];
    }

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payableTransactions(): HasMany
    {
        return $this->hasMany(PayableTransaction::class);
    }

    // Accessors & Mutators for centavos to pesos conversion
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'partial']);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('payment_status', ['unpaid', 'partial']);
    }

    public function scopePaymentOverdue($query)
    {
        return $query->unpaid()
            ->where('payment_due_date', '<', now());
    }
}
