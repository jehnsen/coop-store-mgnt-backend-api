<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPenalty extends Model
{
    use HasUuid, BelongsToStore;
    // No SoftDeletes

    protected $fillable = [
        'uuid',
        'store_id',
        'loan_id',
        'amortization_schedule_id',
        'penalty_type',
        'penalty_rate',
        'days_overdue',
        'penalty_amount',
        'waived_amount',
        'net_penalty',
        'waived_by',
        'waived_at',
        'waiver_reason',
        'applied_date',
        'is_paid',
        'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'applied_date' => 'date',
            'paid_date'    => 'date',
            'waived_at'    => 'datetime',
            'is_paid'      => 'boolean',
            'penalty_rate' => 'decimal:4',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function amortizationSchedule(): BelongsTo
    {
        return $this->belongsTo(LoanAmortizationSchedule::class, 'amortization_schedule_id');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function penaltyAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function waivedAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function netPenalty(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeWaived($query)
    {
        return $query->whereNotNull('waived_by');
    }

    public function scopeOutstanding($query)
    {
        return $query->where('is_paid', false)->where('net_penalty', '>', 0);
    }
}
