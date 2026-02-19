<?php

namespace App\Traits;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            if (auth()->check() && auth()->user()->store_id) {
                $builder->where(static::getStoreColumn(), auth()->user()->store_id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->store_id) && auth()->check()) {
                $model->store_id = auth()->user()->store_id;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function getStoreColumn(): string
    {
        return (new static())->getTable() . '.store_id';
    }
}
