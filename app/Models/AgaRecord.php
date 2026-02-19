<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgaRecord extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'aga_number',
        'meeting_type',
        'meeting_year',
        'meeting_date',
        'venue',
        'total_members',
        'members_present',
        'members_via_proxy',
        'quorum_percentage',
        'quorum_achieved',
        'presiding_officer',
        'secretary',
        'agenda',
        'resolutions_passed',
        'minutes_text',
        'status',
        'finalized_by',
        'finalized_at',
        'notes',
    ];

    protected $casts = [
        'meeting_date'      => 'date',
        'finalized_at'      => 'datetime',
        'quorum_achieved'   => 'boolean',
        'agenda'            => 'array',
        'resolutions_passed'=> 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', 'finalized');
    }

    public function scopeAnnual($query)
    {
        return $query->where('meeting_type', 'annual');
    }

    // -------------------------------------------------------------------------
    // Boot — auto-generate aga_number
    // -------------------------------------------------------------------------

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->aga_number)) {
                $year   = $model->meeting_year ?? now()->year;
                $prefix = $model->meeting_type === 'special' ? 'SGA' : 'AGA';
                $last   = static::withoutGlobalScopes()
                    ->where('aga_number', 'like', "{$prefix}-{$year}-%")
                    ->lockForUpdate()
                    ->count();
                $model->aga_number = sprintf('%s-%d-%02d', $prefix, $year, $last + 1);
            }
        });
    }
}
