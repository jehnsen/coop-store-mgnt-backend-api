<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberShareAccount extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'account_number',
        'share_type',
        'subscribed_shares',
        'par_value_per_share',
        'total_subscribed_amount',
        'total_paid_up_amount',
        'status',
        'opened_date',
        'withdrawn_date',
        'withdrawn_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_date'    => 'date',
            'withdrawn_date' => 'date',
        ];
    }

    // ── Auto-generate account_number on create ────────────────────────────────
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->account_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('account_number', 'like', "SHA-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->account_number = sprintf('SHA-%d-%06d', $year, $last + 1);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ShareCapitalPayment::class, 'share_account_id')->orderBy('payment_date');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(ShareCertificate::class, 'share_account_id')->orderBy('issue_date');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    // ── Centavo accessors/mutators ────────────────────────────────────────────
    protected function parValuePerShare(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalSubscribedAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    protected function totalPaidUpAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Computed accessors ────────────────────────────────────────────────────
    /** Remaining unpaid subscription in PESOS. */
    protected function remainingSubscription(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->getRawOriginal('total_subscribed_amount') - $this->getRawOriginal('total_paid_up_amount')) / 100,
        );
    }

    /** Percentage of subscription paid (0–100). */
    protected function subscriptionPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                $subscribed = $this->getRawOriginal('total_subscribed_amount');
                if ($subscribed === 0) {
                    return 0.0;
                }
                return round(($this->getRawOriginal('total_paid_up_amount') / $subscribed) * 100, 2);
            },
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithdrawn($query)
    {
        return $query->where('status', 'withdrawn');
    }

    public function scopeByMember($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
