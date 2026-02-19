<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareCertificate extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'share_account_id',
        'certificate_number',
        'shares_covered',
        'face_value',
        'issue_date',
        'issued_by',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'   => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    // ── Auto-generate certificate_number on create ────────────────────────────
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->certificate_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('certificate_number', 'like', "SC-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->certificate_number = sprintf('SC-%d-%06d', $year, $last + 1);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function shareAccount(): BelongsTo
    {
        return $this->belongsTo(MemberShareAccount::class, 'share_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Centavo accessor/mutator ──────────────────────────────────────────────
    protected function faceValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) round($value * 100),
        );
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
