<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use BelongsToStore;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'store_id',
        'name',
        'abbreviation',
    ];

    // Relationships
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function conversionFrom(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class, 'from_unit_id');
    }

    public function conversionTo(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class, 'to_unit_id');
    }
}
