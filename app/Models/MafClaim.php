<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A benefit claim filed by a cooperative member (or on their behalf).
 *
 * Lifecycle:  pending → under_review → approved|rejected → paid
 *
 * Auto-generates claim_number on creation: CLAM-YYYY-NNNNNN
 *
 * benefit_type is denormalised from the program at filing time so historical
 * claims retain the correct type even if the program record is later changed.
 *
 * @property int         $id
 * @property string      $uuid
 * @property int         $store_id
 * @property int         $customer_id
 * @property int         $maf_program_id
 * @property int|null    $beneficiary_id
 * @property string      $claim_number
 * @property string      $benefit_type
 * @property \Carbon\Carbon $incident_date
 * @property \Carbon\Carbon $claim_date
 * @property string      $incident_description
 * @property array|null  $supporting_documents
 * @property float       $claimed_amount   In pesos (accessor)
 * @property float|null  $approved_amount  In pesos (accessor)
 * @property string      $status
 */
class MafClaim extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'maf_program_id',
        'beneficiary_id',
        'claim_number',
        'benefit_type',
        'incident_date',
        'claim_date',
        'incident_description',
        'supporting_documents',
        'claimed_amount',
        'approved_amount',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'incident_date'        => 'date',
        'claim_date'           => 'date',
        'supporting_documents' => 'array',
        'reviewed_at'          => 'datetime',
        'approved_at'          => 'datetime',
        'rejected_at'          => 'datetime',
        'paid_at'              => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function claimedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    protected function approvedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value) => $value !== null ? $value / 100 : null,
            set: fn (int|float|null $value) => $value !== null ? (int) round($value * 100) : null,
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function mafProgram(): BelongsTo
    {
        return $this->belongsTo(MafProgram::class, 'maf_program_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(MafBeneficiary::class, 'beneficiary_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(MafClaimPayment::class, 'claim_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate claim_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->claim_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('claim_number', 'like', "CLAM-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->claim_number = sprintf('CLAM-%d-%06d', $year, $last + 1);
            }
        });
    }
}
