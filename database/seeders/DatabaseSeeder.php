<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeds must be run in this specific order due to foreign key dependencies.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting HardwarePOS database seeding...');
        $this->command->newLine();

        // Core setup
        $this->command->info('1/14 - Seeding Store...');
        $this->call(StoreSeeder::class);

        $this->command->info('2/14 - Seeding Branches...');
        $this->call(BranchSeeder::class);

        $this->command->info('3/14 - Seeding Users...');
        $this->call(UserSeeder::class);

        $this->command->info('4/14 - Seeding Categories...');
        $this->call(CategorySeeder::class);

        $this->command->info('5/14 - Seeding Units of Measure...');
        $this->call(UnitOfMeasureSeeder::class);

        // Products and inventory
        $this->command->info('6/14 - Seeding Products (50+ items)...');
        $this->call(ProductSeeder::class);

        $this->command->info('7/14 - Seeding Suppliers...');
        $this->call(SupplierSeeder::class);

        $this->command->info('8/14 - Seeding Customers...');
        $this->call(CustomerSeeder::class);

        // Transactions
        $this->command->info('9/14 - Seeding Sales (200+ transactions over 90 days)...');
        $this->command->warn('⏳ This may take a moment...');
        $this->call(SaleSeeder::class);

        $this->command->info('10/14 - Seeding Credit Transactions...');
        $this->call(CreditTransactionSeeder::class);

        $this->command->info('11/14 - Seeding Stock Adjustments...');
        $this->call(StockAdjustmentSeeder::class);

        $this->command->info('12/15 - Seeding Purchase Orders...');
        $this->call(PurchaseOrderSeeder::class);

        $this->command->info('13/15 - Seeding Payable Transactions (AP invoices & payments)...');
        $this->call(PayableTransactionSeeder::class);

        $this->command->info('14/15 - Seeding Deliveries...');
        $this->call(DeliverySeeder::class);

        $this->command->info('15/15 - Seeding Notifications...');
        $this->call(NotificationSeeder::class);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('📊 Summary:');
        $this->command->line('  - Store: JM Hardware & Construction Supply');
        $this->command->line('  - Branches: 2 (QC Main, Marikina)');
        $this->command->line('  - Users: 8 (1 Owner, 2 Managers, 3 Cashiers, 2 Inventory Staff)');
        $this->command->line('  - Categories: 15');
        $this->command->line('  - Products: 50+');
        $this->command->line('  - Suppliers: 5');
        $this->command->line('  - Customers: 10');
        $this->command->line('  - Sales: 200+ over last 90 days');
        $this->command->line('  - Purchase Orders: 10 (with AP invoices)');
        $this->command->line('  - Payable Transactions: AP invoices & payments');
        $this->command->line('  - Deliveries: 15');
        $this->command->newLine();
        $this->command->info('🔐 Login Credentials:');
        $this->command->line('  Email: juan@jmhardware.ph');
        $this->command->line('  Password: password');
        $this->command->newLine();
    }
}
