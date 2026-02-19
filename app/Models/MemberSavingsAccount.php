<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberSavingsAccount extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'account_number',
        'savings_type',
        'current_balance',
        'minimum_balance',
        'interest_rate',
        'total_deposited',
        'total_withdrawn',
        'total_interest_earned',
        'status',
        'opened_date',
        'closed_date',
        'closed_by',
        'last_transaction_date',
        'notes',
    ];

    protected $casts = [
        'opened_date'            => 'date',
        'closed_date'            => 'date',
        'last_transaction_date'  => 'date',
        'interest_rate'          => 'decimal:6',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function currentBalance(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function minimumBalance(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function totalDeposited(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function totalWithdrawn(): Attribute
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

    // Available balance above minimum_balance
    protected function availableForWithdrawal(): Attribute
    {
        return Attribute::make(
            get: function () {
                $balance = $this->getRawOriginal('current_balance');
                $minimum = $this->getRawOriginal('minimum_balance');
                return max(0, $balance - $minimum) / 100;
            }
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
        return $this->hasMany(SavingsTransaction::class, 'savings_account_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDormant($query)
    {
        return $query->where('status', 'dormant');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeVoluntary($query)
    {
        return $query->where('savings_type', 'voluntary');
    }

    public function scopeCompulsory($query)
    {
        return $query->where('savings_type', 'compulsory');
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
                    ->where('account_number', 'like', "SVA-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->account_number = sprintf('SVA-%d-%06d', $year, $last + 1);
            }
        });
    }
}
