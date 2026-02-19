<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable disbursement record for a paid MAF claim.
 *
 * One claim has at most one payment record. No SoftDeletes — follows the same
 * immutable-ledger convention as LoanPayment and SavingsTransaction.
 *
 * Auto-generates payment_number on creation: MAFP-YYYY-NNNNNN
 *
 * @property int    $id
 * @property string $uuid
 * @property int    $store_id
 * @property int    $claim_id
 * @property int    $customer_id
 * @property int    $user_id
 * @property string $payment_number
 * @property float  $amount          In pesos (accessor); stored centavos.
 * @property string $payment_method
 * @property string|null $reference_number
 * @property \Carbon\Carbon $payment_date
 * @property string|null $notes
 */
class MafClaimPayment extends Model
{
    use HasUuid, BelongsToStore;
    // No SoftDeletes — immutable ledger

    protected $fillable = [
        'uuid',
        'store_id',
        'claim_id',
        'customer_id',
        'user_id',
        'payment_number',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
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

    public function claim(): BelongsTo
    {
        return $this->belongsTo(MafClaim::class, 'claim_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate payment_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->payment_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('payment_number', 'like', "MAFP-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->payment_number = sprintf('MAFP-%d-%06d', $year, $last + 1);
            }
        });
    }
}
