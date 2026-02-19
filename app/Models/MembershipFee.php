<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipFee extends Model
{
    use HasUuid, BelongsToStore;

    // NO SoftDeletes — immutable financial ledger; use is_reversed flag

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'user_id',
        'application_id',
        'fee_number',
        'fee_type',
        'amount',
        'payment_method',
        'reference_number',
        'transaction_date',
        'period_year',
        'notes',
        'is_reversed',
        'reversed_at',
        'reversed_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'reversed_at'      => 'datetime',
        'is_reversed'      => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessor / Mutator
    // -------------------------------------------------------------------------

    protected function amount(): Attribute
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'application_id');
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

    // -------------------------------------------------------------------------
    // Boot — auto-generate fee_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->fee_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('fee_number', 'like', "MFE-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->fee_number = sprintf('MFE-%d-%06d', $year, $last + 1);
            }
        });
    }
}
