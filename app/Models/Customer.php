<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Customer extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'code',
        'name',
        'type',
        'company_name',
        'tin',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'province',
        'postal_code',
        'credit_limit',
        'credit_terms_days',
        'total_outstanding',
        'total_purchases',
        'payment_rating',
        'notes',
        'is_active',
        'allow_credit',
        // MPC member fields (added 2026-02-19)
        'is_member',
        'member_id',
        'accumulated_patronage',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'allow_credit' => 'boolean',
            'is_member'    => 'boolean',
        ];
    }

    // Relationships
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'recipient_phone', 'phone');
    }

    /** MPC: Restricted credit wallets belonging to this member. */
    public function wallets(): HasMany
    {
        return $this->hasMany(CustomerWallet::class);
    }

    // Accessors & Mutators for centavos to pesos conversion
    protected function creditLimit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function totalOutstanding(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    protected function totalPurchases(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    /**
     * MPC: accumulated_patronage is stored as centavos (bigInteger).
     * Returns pesos via accessor; mutator converts pesos input → centavos.
     */
    protected function accumulatedPatronage(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCredit($query)
    {
        return $query->where('allow_credit', true);
    }

    public function scopeWithOutstanding($query)
    {
        return $query->where('total_outstanding', '>', 0);
    }
}
