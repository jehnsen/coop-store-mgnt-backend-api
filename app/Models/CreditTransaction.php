<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use HasUuid, BelongsToStore;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'sale_id',
        'wallet_id',   // MPC: FK to customer_wallets; null for legacy credit payments
        'user_id',
        'type',
        'reference_number',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'transaction_date',
        'due_date',
        'paid_date',
        'payment_method',
        'notes',
        'is_reversed',
        'reversed_at',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'due_date' => 'date',
            'paid_date' => 'date',
            'reversed_at' => 'datetime',
            'is_reversed' => 'boolean',
        ];
    }

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** MPC: The specific wallet that was charged for this transaction (nullable). */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }

    // Accessors & Mutators for centavos to pesos conversion
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function balanceBefore(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // Scopes
    public function scopeCharges($query)
    {
        return $query->where('type', 'charge');
    }

    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    public function scopeOverdue($query)
    {
        return $query->where('type', 'charge')
            ->where('due_date', '<', now())
            ->whereNull('paid_date');
    }
}
