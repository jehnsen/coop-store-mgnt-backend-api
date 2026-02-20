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
        $store      = Store::first();
        $mainBranch = Branch::where('is_main', true)->first();
        $satellite  = Branch::where('is_main', false)->first();

        // 1. General Manager / Administrator
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Danilo Macaraeg',
            'email'             => 'danilo.macaraeg@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0917-456-7801',
            'role'              => 'owner',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(2),
        ]);

        // 2. Store Manager – Poblacion Main
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Evelyn Buenaventura',
            'email'             => 'evelyn.buenaventura@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0918-321-6540',
            'role'              => 'manager',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(3),
        ]);

        // 3. Store Manager – Satellite (Brgy. Tagumpay)
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $satellite->id,
            'name'              => 'Rodrigo Palabay',
            'email'             => 'rodrigo.palabay@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0919-654-3217',
            'role'              => 'manager',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(5),
        ]);

        // 4. Cashier / Teller – Main (POS & loan/savings counter)
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Rowena Castillo',
            'email'             => 'rowena.castillo@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0920-789-4561',
            'role'              => 'cashier',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subMinutes(45),
        ]);

        // 5. Cashier / Teller – Main (second window)
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Noel Domingo',
            'email'             => 'noel.domingo@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0921-012-3456',
            'role'              => 'cashier',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(1),
        ]);

        // 6. Cashier / Teller – Satellite
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $satellite->id,
            'name'              => 'Gloria Reyes',
            'email'             => 'gloria.reyes@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0922-345-6789',
            'role'              => 'cashier',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(2),
        ]);

        // 7. Loan Officer
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Arsenio Valdez',
            'email'             => 'arsenio.valdez@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0923-678-9012',
            'role'              => 'loan_officer',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(4),
        ]);

        // 8. Inventory / Bodega Staff – Main
        User::create([
            'uuid'              => Str::uuid(),
            'store_id'          => $store->id,
            'branch_id'         => $mainBranch->id,
            'name'              => 'Benedicto Tolentino',
            'email'             => 'benedicto.tolentino@snlsimpc.coop',
            'password'          => Hash::make('password'),
            'phone'             => '0924-901-2345',
            'role'              => 'inventory_staff',
            'is_active'         => true,
            'email_verified_at' => now(),
            'last_login_at'     => now()->subHours(6),
        ]);
    }
}
