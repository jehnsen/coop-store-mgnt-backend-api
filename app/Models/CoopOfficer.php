<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoopOfficer extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'name',
        'position',
        'committee',
        'term_from',
        'term_to',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'term_from' => 'date',
        'term_to'   => 'date',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCommittee($query, string $committee)
    {
        return $query->where('committee', $committee);
    }
}
