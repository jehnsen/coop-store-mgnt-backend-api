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

        Branch::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'name' => 'JM Hardware QC Main',
            'address' => '123 Commonwealth Avenue, Barangay Holy Spirit',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'phone' => '(02) 8123-4567',
            'is_main' => true,
            'is_active' => true,
        ]);

        Branch::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'name' => 'JM Hardware Marikina',
            'address' => '456 J.P. Rizal Street, Barangay Sta. Elena',
            'city' => 'Marikina City',
            'province' => 'Metro Manila',
            'phone' => '(02) 8987-6543',
            'is_main' => false,
            'is_active' => true,
        ]);
    }
}
