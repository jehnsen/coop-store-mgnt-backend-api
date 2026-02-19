<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $mainBranch = Branch::where('is_main', true)->first();
        $marikina = Branch::where('is_main', false)->first();

        // 1. Owner
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $mainBranch->id,
            'name' => 'Juan Cruz',
            'email' => 'juan@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0917-123-4567',
            'role' => 'owner',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(2),
        ]);

        // 2. Manager - QC Main
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $mainBranch->id,
            'name' => 'Maria Santos',
            'email' => 'maria.santos@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0918-234-5678',
            'role' => 'manager',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(4),
        ]);

        // 3. Manager - Marikina
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $marikina->id,
            'name' => 'Roberto Garcia',
            'email' => 'roberto.garcia@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0919-345-6789',
            'role' => 'manager',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(5),
        ]);

        // 4. Cashier - QC Main
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $mainBranch->id,
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0920-456-7890',
            'role' => 'cashier',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subMinutes(30),
        ]);

        // 5. Cashier - QC Main
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $mainBranch->id,
            'name' => 'Pedro Lopez',
            'email' => 'pedro.lopez@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0921-567-8901',
            'role' => 'cashier',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subDays(1),
        ]);

        // 6. Cashier - Marikina
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $marikina->id,
            'name' => 'Sofia Ramos',
            'email' => 'sofia.ramos@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0922-678-9012',
            'role' => 'cashier',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(1),
        ]);

        // 7. Inventory Staff - QC Main
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $mainBranch->id,
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0923-789-0123',
            'role' => 'inventory_staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(6),
        ]);

        // 8. Inventory Staff - Marikina
        User::create([
            'uuid' => Str::uuid(),
            'store_id' => $store->id,
            'branch_id' => $marikina->id,
            'name' => 'Linda Fernandez',
            'email' => 'linda.fernandez@jmhardware.ph',
            'password' => Hash::make('password'),
            'phone' => '0924-890-1234',
            'role' => 'inventory_staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now()->subHours(8),
        ]);
    }
}
