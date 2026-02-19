<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    use HasUuid, BelongsToStore;
    // No SoftDeletes — immutable ledger

    protected $fillable = [
        'uuid',
        'store_id',
        'loan_id',
        'customer_id',
        'user_id',
        'payment_number',
        'amount',
        'principal_portion',
        'interest_portion',
        'penalty_portion',
        'balance_before',
        'balance_after',
        'payment_method',
        'reference_number',
        'payment_date',
        'notes',
        'is_reversed',
        'reversed_at',
        'reversed_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'reversed_at'  => 'datetime',
            'is_reversed'  => 'boolean',
        ];
    }

    // ── Auto-generate payment_number on create ────────────────────────────────
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->payment_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('payment_number', 'like', "LP-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->payment_number = sprintf('LP-%d-%06d', $year, $last + 1);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function principalPortion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function interestPortion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function penaltyPortion(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function balanceBefore(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_reversed', false);
    }
}
