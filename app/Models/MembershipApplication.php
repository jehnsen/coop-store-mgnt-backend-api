<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipApplication extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'application_number',
        'application_type',
        'application_date',
        'civil_status',
        'occupation',
        'employer',
        'monthly_income_range',
        'beneficiary_info',
        'admission_fee_amount',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'application_date' => 'date',
        'reviewed_at'      => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessor / Mutator
    // -------------------------------------------------------------------------

    protected function admissionFeeAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(MembershipFee::class, 'application_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate application_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->application_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('application_number', 'like', "APP-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->application_number = sprintf('APP-%d-%06d', $year, $last + 1);
            }
        });
    }
}
