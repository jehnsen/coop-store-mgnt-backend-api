<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeDepositTransaction extends Model
{
    use HasUuid, BelongsToStore;
    // NO SoftDeletes — immutable ledger

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'time_deposit_id',
        'user_id',
        'transaction_number',
        'transaction_type',
        'amount',
        'interest_amount',
        'penalty_amount',
        'balance_before',
        'balance_after',
        'payment_method',
        'reference_number',
        'transaction_date',
        'period_from',
        'period_to',
        'notes',
        'is_reversed',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'period_from'      => 'date',
        'period_to'        => 'date',
        'reversed_at'      => 'datetime',
        'is_reversed'      => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function interestAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function penaltyAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function balanceBefore(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function timeDeposit(): BelongsTo
    {
        return $this->belongsTo(TimeDeposit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_reversed', false);
    }

    public function scopeReversed($query)
    {
        return $query->where('is_reversed', true);
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate transaction_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->transaction_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('transaction_number', 'like', "TDT-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->transaction_number = sprintf('TDT-%d-%06d', $year, $last + 1);
            }
        });
    }
}
