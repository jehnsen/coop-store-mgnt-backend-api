<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MAF benefit program definition (e.g. "Death Benefit – ₱10,000").
 *
 * @property int         $id
 * @property string      $uuid
 * @property int         $store_id
 * @property string      $code
 * @property string      $name
 * @property string|null $description
 * @property string      $benefit_type   death|hospitalization|disability|calamity|funeral
 * @property float       $benefit_amount In pesos (accessor); stored as centavos.
 * @property int         $waiting_period_days
 * @property int|null    $max_claims_per_year
 * @property bool        $is_active
 */
class MafProgram extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'code',
        'name',
        'description',
        'benefit_type',
        'benefit_amount',
        'waiting_period_days',
        'max_claims_per_year',
        'is_active',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'waiting_period_days' => 'integer',
        'max_claims_per_year' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Centavo Accessors / Mutators
    // -------------------------------------------------------------------------

    protected function benefitAmount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(MafClaim::class, 'maf_program_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBenefitType($query, string $type)
    {
        return $query->where('benefit_type', $type);
    }
}
