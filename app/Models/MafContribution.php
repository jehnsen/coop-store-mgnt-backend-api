<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry for a member's MAF fund contribution.
 *
 * No SoftDeletes — errors are corrected via the reversal fields, following the
 * same convention as LoanPayment and SavingsTransaction.
 *
 * Auto-generates contribution_number on creation: MAFC-YYYY-NNNNNN
 *
 * @property int         $id
 * @property string      $uuid
 * @property int         $store_id
 * @property int         $customer_id
 * @property int         $user_id
 * @property string      $contribution_number
 * @property float       $amount             In pesos (accessor); stored centavos.
 * @property string      $payment_method
 * @property string|null $reference_number
 * @property \Carbon\Carbon $contribution_date
 * @property int         $period_year
 * @property int|null    $period_month
 * @property string|null $notes
 * @property bool        $is_reversed
 */
class MafContribution extends Model
{
    use HasUuid, BelongsToStore;
    // No SoftDeletes — immutable ledger

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'user_id',
        'contribution_number',
        'amount',
        'payment_method',
        'reference_number',
        'contribution_date',
        'period_year',
        'period_month',
        'notes',
        'is_reversed',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'contribution_date' => 'date',
        'reversed_at'       => 'datetime',
        'is_reversed'       => 'boolean',
        'period_year'       => 'integer',
        'period_month'      => 'integer',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversedByUser(): BelongsTo
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

    public function scopeReversed($query)
    {
        return $query->where('is_reversed', true);
    }

    public function scopeForPeriod($query, int $year, ?int $month = null)
    {
        $query->where('period_year', $year);

        if ($month !== null) {
            $query->where('period_month', $month);
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate contribution_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->contribution_number)) {
                $year = now()->year;
                $last = static::withoutGlobalScopes()
                    ->where('contribution_number', 'like', "MAFC-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->contribution_number = sprintf('MAFC-%d-%06d', $year, $last + 1);
            }
        });
    }
}
