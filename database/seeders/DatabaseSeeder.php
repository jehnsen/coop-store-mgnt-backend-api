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
        $this->command->info('Starting SNLSI MPC database seeding...');
        $this->command->newLine();

        // ── GROUP 1: Core Setup ───────────────────────────────────────────────
        $this->command->info('1/23 - Seeding Store...');
        $this->call(StoreSeeder::class);

        $this->command->info('2/23 - Seeding Branches...');
        $this->call(BranchSeeder::class);

        $this->command->info('3/23 - Seeding Users...');
        $this->call(UserSeeder::class);

        $this->command->info('4/23 - Seeding Categories...');
        $this->call(CategorySeeder::class);

        $this->command->info('5/23 - Seeding Units of Measure...');
        $this->call(UnitOfMeasureSeeder::class);

        // ── GROUP 2: Products, Suppliers & Customers ─────────────────────────
        $this->command->info('6/23 - Seeding Products (52 agri/grocery items)...');
        $this->call(ProductSeeder::class);

        $this->command->info('7/23 - Seeding Suppliers...');
        $this->call(SupplierSeeder::class);

        $this->command->info('8/23 - Seeding Customers & Members...');
        $this->call(CustomerSeeder::class);

        // ── GROUP 3: Retail / POS Transactions ───────────────────────────────
        $this->command->info('9/23 - Seeding Sales (200+ transactions over 90 days)...');
        $this->command->warn('This may take a moment...');
        $this->call(SaleSeeder::class);

        $this->command->info('10/23 - Seeding Credit Transactions...');
        $this->call(CreditTransactionSeeder::class);

        $this->command->info('11/23 - Seeding Stock Adjustments...');
        $this->call(StockAdjustmentSeeder::class);

        $this->command->info('12/23 - Seeding Purchase Orders...');
        $this->call(PurchaseOrderSeeder::class);

        $this->command->info('13/23 - Seeding Payable Transactions (AP invoices & payments)...');
        $this->call(PayableTransactionSeeder::class);

        $this->command->info('14/23 - Seeding Deliveries...');
        $this->call(DeliverySeeder::class);

        $this->command->info('15/23 - Seeding Notifications...');
        $this->call(NotificationSeeder::class);

        // ── GROUP 4: MPC / Cooperative Finance ───────────────────────────────
        $this->command->newLine();
        $this->command->info('--- MPC Cooperative Finance Module ---');

        $this->command->info('16/23 - Seeding Memberships (applications, fees)...');
        $this->call(MembershipSeeder::class);

        $this->command->info('17/23 - Seeding Member Share Accounts (accounts, payments, certificates)...');
        $this->call(MemberShareAccountSeeder::class);

        $this->command->info('18/23 - Seeding Member Savings Accounts (accounts, transactions)...');
        $this->call(MemberSavingsAccountSeeder::class);

        $this->command->info('19/23 - Seeding Time Deposits (placements, transactions)...');
        $this->call(TimeDepositSeeder::class);

        $this->command->info('20/23 - Seeding Loan Products & Loans (amortization, payments)...');
        $this->call(LoanSeeder::class);

        $this->command->info('21/23 - Seeding Patronage Refunds (FY2024 completed, FY2025 distributing)...');
        $this->call(PatronageRefundSeeder::class);

        $this->command->info('22/23 - Seeding Cooperative Governance (officers, AGA, CDA reports)...');
        $this->call(CoopGovernanceSeeder::class);

        $this->command->info('23/23 - Seeding Mutual Aid Fund (programs, contributions, claims)...');
        $this->call(MafSeeder::class);

        // ── Done ──────────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->line('  Store   : Samahang Nayon ng Lungsod ng San Isidro MPC (SNLSI MPC)');
        $this->command->line('  Branches: 2 (Poblacion Main, Brgy. Tagumpay Satellite)');
        $this->command->line('  Users   : 8 (GM, 2 Managers, 3 Cashiers/Tellers, Loan Officer, Inventory)');
        $this->command->line('  Products: 52 (agri supplies + grocery)');
        $this->command->line('  Suppliers: 6 (Nueva Ecija-based)');
        $this->command->line('  Members : 10 active + 1 pending + 2 non-members');
        $this->command->line('  Sales   : 200+ over last 90 days');
        $this->command->line('  --- MPC Finance ---');
        $this->command->line('  Memberships    : 10 approved + 1 pending application');
        $this->command->line('  Share Accounts : 10 (6 fully paid, 4 partial)');
        $this->command->line('  Savings Accts  : 14 (10 compulsory + 4 voluntary)');
        $this->command->line('  Time Deposits  : 3 (2 active, 1 rolled-over)');
        $this->command->line('  Loans          : 4 (1 closed, 2 active, 1 pending)');
        $this->command->line('  Patronage      : FY2024 completed, FY2025 distributing');
        $this->command->line('  Officers       : 7 BOD + 3 Audit + 3 Election Committee');
        $this->command->line('  AGA Records    : 2024 & 2025 (both finalized)');
        $this->command->line('  CDA Reports    : 2024 submitted, 2025 draft');
        $this->command->line('  MAF Programs   : 5 | Contributions: 18 | Claims: 2');
        $this->command->newLine();
        $this->command->info('Login Credentials:');
        $this->command->line('  GM      : danilo.macaraeg@snlsimpc.coop  / password');
        $this->command->line('  Manager : evelyn.buenaventura@snlsimpc.coop / password');
        $this->command->line('  Cashier : rowena.castillo@snlsimpc.coop  / password');
        $this->command->newLine();
    }
}
