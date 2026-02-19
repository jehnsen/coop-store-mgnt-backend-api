<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'code',
        'name',
        'description',
        'loan_type',
        'interest_rate',
        'interest_method',
        'max_term_months',
        'min_amount',
        'max_amount',
        'processing_fee_rate',
        'service_fee',
        'requires_collateral',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_collateral' => 'boolean',
            'is_active'           => 'boolean',
            'interest_rate'       => 'decimal:4',
            'processing_fee_rate' => 'decimal:4',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function minAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function maxAmount(): Attribute
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

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('loan_type', $type);
    }
}
