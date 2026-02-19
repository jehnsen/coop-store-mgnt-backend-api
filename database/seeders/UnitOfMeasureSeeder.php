<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        $units = [
            ['name' => 'piece', 'abbreviation' => 'pcs'],
            ['name' => 'bag', 'abbreviation' => 'bag'],
            ['name' => 'sack', 'abbreviation' => 'sack'],
            ['name' => 'kilogram', 'abbreviation' => 'kg'],
            ['name' => 'meter', 'abbreviation' => 'm'],
            ['name' => 'foot', 'abbreviation' => 'ft'],
            ['name' => 'gallon', 'abbreviation' => 'gal'],
            ['name' => 'liter', 'abbreviation' => 'L'],
            ['name' => 'roll', 'abbreviation' => 'roll'],
            ['name' => 'box', 'abbreviation' => 'box'],
            ['name' => 'bundle', 'abbreviation' => 'bundle'],
            ['name' => 'length', 'abbreviation' => 'length'],
            ['name' => 'sheet', 'abbreviation' => 'sheet'],
            ['name' => 'square meter', 'abbreviation' => 'sq.m'],
            ['name' => 'cubic meter', 'abbreviation' => 'cu.m'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::create([
                'store_id' => $store->id,
                'name' => $unit['name'],
                'abbreviation' => $unit['abbreviation'],
            ]);
        }
    }
}
