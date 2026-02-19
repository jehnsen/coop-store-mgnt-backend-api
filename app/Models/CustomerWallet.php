<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a restricted credit facility for a cooperative member.
 *
 * A member may hold multiple wallets, each scoped to specific product categories.
 * The CreditService is responsible for enforcing the category restriction at
 * sale time via validateWalletUsage().
 *
 * @property int    $id
 * @property string $uuid
 * @property int    $customer_id
 * @property string $name             e.g. "Grocery Credit", "Rice Production Loan"
 * @property float  $balance          Available balance in PESOS (via accessor)
 * @property float  $credit_limit     Maximum credit in PESOS (via accessor)
 * @property array  $allowed_category_ids  JSON array of Category.id values
 * @property string $status           'active' | 'frozen'
 */
class CustomerWallet extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'customer_id',
        'name',
        'balance',
        'credit_limit',
        'allowed_category_ids',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Automatically JSON-encodes on save and JSON-decodes on read.
            // Always yields a plain PHP array of integers (category IDs).
            'allowed_category_ids' => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** All credit transactions that were charged to this specific wallet. */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'wallet_id');
    }

    // -------------------------------------------------------------------------
    // Accessors & Mutators – centavos ↔ pesos (project-wide convention)
    // -------------------------------------------------------------------------

    /**
     * balance is stored as centavos (bigInteger) in the database.
     * Accessor returns pesos; mutator accepts pesos and converts to centavos.
     *
     * Use getRawOriginal('balance') wherever centavo-level arithmetic is needed.
     */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    /** Same centavo convention as balance. */
    protected function creditLimit(): Attribute
    {
        return Attribute::make(
            get: fn (int $value) => $value / 100,
            set: fn (int|float $value) => (int) round($value * 100),
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query): void
    {
        $query->where('status', 'active');
    }

    public function scopeFrozen($query): void
    {
        $query->where('status', 'frozen');
    }

    // -------------------------------------------------------------------------
    // Business-Logic Helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether the wallet has at least $amountCentavos of available balance.
     * Operates on the raw DB value to avoid double-division through the accessor.
     */
    public function hasAvailableBalance(int $amountCentavos): bool
    {
        return $this->getRawOriginal('balance') >= $amountCentavos;
    }

    /**
     * Return true if the given category ID is whitelisted for this wallet.
     */
    public function allowsCategory(int $categoryId): bool
    {
        return in_array($categoryId, $this->allowed_category_ids ?? [], strict: true);
    }

    /**
     * Convenience: returns true only when the wallet is usable (active + funded).
     */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && $this->getRawOriginal('balance') > 0;
    }
}
