<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class TimeDeposit extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'account_number',
        'principal_amount',
        'interest_rate',
        'interest_method',
        'payment_frequency',
        'term_months',
        'early_withdrawal_penalty_rate',
        'placement_date',
        'maturity_date',
        'current_balance',
        'total_interest_earned',
        'expected_interest',
        'status',
        'matured_at',
        'pre_terminated_at',
        'pre_terminated_by',
        'pre_termination_reason',
        'rollover_count',
        'parent_time_deposit_id',
        'notes',
    ];

    protected $casts = [
        'placement_date'      => 'date',
        'maturity_date'       => 'date',
        'matured_at'          => 'datetime',
        'pre_terminated_at'   => 'datetime',
        'interest_rate'       => 'decimal:6',
        'early_withdrawal_penalty_rate' => 'decimal:4',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function principalAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function currentBalance(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function totalInterestEarned(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function expectedInterest(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    // Convenience: days remaining until maturity (negative = already past)
    protected function daysToMaturity(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) now()->startOfDay()->diffInDays($this->maturity_date, false),
        );
    }

    protected function isMatured(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'matured'
                || ($this->status === 'active' && $this->maturity_date->lte(now())),
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TimeDepositTransaction::class);
    }

    public function preTerminatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pre_terminated_by');
    }

    /** The TD that was rolled over to create this one. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TimeDeposit::class, 'parent_time_deposit_id');
    }

    /** TDs created by rolling over this one. */
    public function renewals(): HasMany
    {
        return $this->hasMany(TimeDeposit::class, 'parent_time_deposit_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeMatured($query)
    {
        return $query->where('status', 'matured');
    }

    public function scopePreTerminated($query)
    {
        return $query->where('status', 'pre_terminated');
    }

    /** Active TDs maturing within the next $days calendar days. */
    public function scopeMaturing($query, int $days = 30)
    {
        return $query->where('status', 'active')
            ->whereBetween('maturity_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeByMember($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate account_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->account_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('account_number', 'like', "TD-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->account_number = sprintf('TD-%d-%06d', $year, $last + 1);
            }
        });
    }
}
