<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldTransaction extends Model
{
    use HasUuid, BelongsToStore;

    protected $fillable = [
        'uuid',
        'store_id',
        'branch_id',
        'user_id',
        'hold_number',
        'name',
        'cart_data',
        'notes',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now());
    }
}
