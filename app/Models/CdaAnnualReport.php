<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CdaAnnualReport extends Model
{
    use HasUuid, BelongsToStore, SoftDeletes;

    protected $fillable = [
        'uuid',
        'store_id',
        'report_year',
        'period_from',
        'period_to',
        'cda_reg_number',
        'cooperative_type',
        'area_of_operation',
        'report_data',
        'status',
        'compiled_by',
        'compiled_at',
        'finalized_by',
        'finalized_at',
        'submitted_date',
        'submission_reference',
        'notes',
    ];

    protected $casts = [
        'period_from'  => 'date',
        'period_to'    => 'date',
        'compiled_at'  => 'datetime',
        'finalized_at' => 'datetime',
        'submitted_date'=> 'date',
        'report_data'  => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function compiledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
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

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }
}
