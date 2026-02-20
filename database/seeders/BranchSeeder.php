<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();

        // Main store at the Poblacion (town center)
        Branch::create([
            'uuid'     => Str::uuid(),
            'store_id' => $store->id,
            'name'     => 'SNLSI MPC – Poblacion Main Store',
            'address'  => 'Purok 3, Barangay Poblacion',
            'city'     => 'San Isidro',
            'province' => 'Nueva Ecija',
            'phone'    => '(044) 940-1234',
            'is_main'  => true,
            'is_active' => true,
        ]);

        // Satellite store serving the far-flung barangays
        Branch::create([
            'uuid'     => Str::uuid(),
            'store_id' => $store->id,
            'name'     => 'SNLSI MPC – Brgy. Tagumpay Satellite Store',
            'address'  => 'Purok 1, Barangay Tagumpay',
            'city'     => 'San Isidro',
            'province' => 'Nueva Ecija',
            'phone'    => '0917-856-2341',
            'is_main'  => false,
            'is_active' => true,
        ]);
    }
}
