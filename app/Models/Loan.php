<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'loan_number',
        'customer_id',
        'loan_product_id',
        'user_id',
        'approved_by',
        'disbursed_by',
        'principal_amount',
        'interest_rate',
        'interest_method',
        'term_months',
        'payment_interval',
        'purpose',
        'collateral_description',
        'processing_fee',
        'service_fee',
        'net_proceeds',
        'total_interest',
        'total_payable',
        'amortization_amount',
        'outstanding_balance',
        'total_principal_paid',
        'total_interest_paid',
        'total_penalty_paid',
        'total_penalties_outstanding',
        'application_date',
        'approval_date',
        'disbursement_date',
        'first_payment_date',
        'maturity_date',
        'status',
        'rejection_reason',
        'restructured_from_loan_id',
    ];

    protected function casts(): array
    {
        return [
            'application_date'  => 'date',
            'approval_date'     => 'date',
            'disbursement_date' => 'date',
            'first_payment_date' => 'date',
            'maturity_date'     => 'date',
            'interest_rate'     => 'decimal:4',
        ];
    }

    // ── Auto-generate loan_number on create ───────────────────────────────────
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->loan_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('loan_number', 'like', "LN-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->loan_number = sprintf('LN-%d-%06d', $year, $last + 1);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function amortizationSchedules(): HasMany
    {
        return $this->hasMany(LoanAmortizationSchedule::class)->orderBy('payment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class)->orderBy('payment_date');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LoanPenalty::class)->orderBy('applied_date');
    }

    public function restructuredFrom(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'restructured_from_loan_id');
    }

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function principalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function processingFee(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function serviceFee(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function netProceeds(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalInterest(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPayable(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function amortizationAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function outstandingBalance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPrincipalPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalInterestPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPenaltyPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPenaltiesOutstanding(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Computed accessors ────────────────────────────────────────────────────
    /** Whether this loan has any overdue amortization schedules. */
    protected function isDelinquent(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->amortizationSchedules()
                ->where('status', 'overdue')
                ->orWhere(fn ($q) => $q->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString()))
                ->exists(),
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDelinquent($query)
    {
        return $query->whereHas('amortizationSchedules', function ($q) {
            $q->where('status', 'overdue')
              ->orWhere(fn ($sq) => $sq->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString()));
        });
    }

    public function scopeMaturing($query, int $days)
    {
        return $query->where('status', 'active')
            ->whereBetween('maturity_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
