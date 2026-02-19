<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A beneficiary pre-registered by a cooperative member.
 *
 * Used when filing death or hospitalization claims on behalf of the member
 * or a family member. A member may register multiple beneficiaries; exactly
 * one should have is_primary = true.
 *
 * @property int         $id
 * @property string      $uuid
 * @property int         $store_id
 * @property int         $customer_id
 * @property string      $name
 * @property string      $relationship  spouse|child|parent|sibling|other
 * @property \Carbon\Carbon|null $birth_date
 * @property string|null $contact_number
 * @property bool        $is_primary
 * @property bool        $is_active
 * @property string|null $notes
 */
class MafBeneficiary extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'name',
        'relationship',
        'birth_date',
        'contact_number',
        'is_primary',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(MafClaim::class, 'beneficiary_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
