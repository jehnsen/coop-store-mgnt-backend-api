<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAmortizationSchedule extends Model
{
    // No UUID, no BelongsToStore, no SoftDeletes — child of Loan
    protected $fillable = [
        'loan_id',
        'payment_number',
        'due_date',
        'beginning_balance',
        'principal_due',
        'interest_due',
        'total_due',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'total_paid',
        'ending_balance',
        'paid_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date'  => 'date',
            'paid_date' => 'date',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(LoanPenalty::class, 'amortization_schedule_id');
    }

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function beginningBalance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function principalDue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function interestDue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalDue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function principalPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function interestPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function penaltyPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function endingBalance(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Computed accessors ────────────────────────────────────────────────────
    /** Whether this schedule entry is currently overdue. */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'overdue'
                || (in_array($this->status, ['pending', 'partial']) && $this->due_date < now()->startOfDay()),
        );
    }

    /** Remaining amount due in pesos (total_due - total_paid). */
    protected function remainingDue(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->getRawOriginal('total_due') - $this->getRawOriginal('total_paid')) / 100,
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'overdue')
              ->orWhere(fn ($sq) => $sq->whereIn('status', ['pending', 'partial'])->where('due_date', '<', now()->toDateString()));
        });
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
