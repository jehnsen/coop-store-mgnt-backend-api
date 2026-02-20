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
            // Weight
            ['name' => 'kilogram',      'abbreviation' => 'kg'],
            ['name' => 'gram',          'abbreviation' => 'g'],
            ['name' => 'sack (50kg)',   'abbreviation' => 'sack'],
            ['name' => 'bag',           'abbreviation' => 'bag'],

            // Volume / Liquid
            ['name' => 'liter',         'abbreviation' => 'L'],
            ['name' => 'milliliter',    'abbreviation' => 'mL'],
            ['name' => 'gallon',        'abbreviation' => 'gal'],

            // Count / Piece
            ['name' => 'piece',         'abbreviation' => 'pcs'],
            ['name' => 'pack',          'abbreviation' => 'pack'],
            ['name' => 'box',           'abbreviation' => 'box'],
            ['name' => 'bundle',        'abbreviation' => 'bundle'],
            ['name' => 'roll',          'abbreviation' => 'roll'],
            ['name' => 'dozen',         'abbreviation' => 'doz'],
            ['name' => 'set',           'abbreviation' => 'set'],

            // Length / Area
            ['name' => 'meter',         'abbreviation' => 'm'],
            ['name' => 'foot',          'abbreviation' => 'ft'],
            ['name' => 'square meter',  'abbreviation' => 'sq.m'],

            // Agri-specific
            ['name' => 'can (330mL)',   'abbreviation' => 'can'],
            ['name' => 'tablet',        'abbreviation' => 'tab'],
            ['name' => 'ampule',        'abbreviation' => 'amp'],
            ['name' => 'sachet',        'abbreviation' => 'sachet'],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::create([
                'store_id'     => $store->id,
                'name'         => $unit['name'],
                'abbreviation' => $unit['abbreviation'],
            ]);
        }
    }
}
