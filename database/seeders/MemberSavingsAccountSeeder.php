<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds member_savings_accounts and savings_transactions.
 *
 * Each active member has:
 *  - 1 compulsory savings account (required, low balance, monthly deduction)
 *  - Some members also have a voluntary savings account
 * Transactions: deposits, withdrawals, compulsory_deductions, interest_credits.
 */
class MemberSavingsAccountSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $cashier = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();

        // member_code => [compulsory_balance, voluntary_balance (null = no voluntary)]
        $members = [
            'MBR-001' => ['compulsory' => 280000,  'voluntary' => 450000],   // ₱2,800 / ₱4,500
            'MBR-002' => ['compulsory' => 195000,  'voluntary' => 620000],   // ₱1,950 / ₱6,200
            'MBR-003' => ['compulsory' => 420000,  'voluntary' => null],     // ₱4,200
            'MBR-004' => ['compulsory' => 105000,  'voluntary' => null],     // ₱1,050
            'MBR-005' => ['compulsory' => 380000,  'voluntary' => 850000],   // ₱3,800 / ₱8,500
            'MBR-006' => ['compulsory' => 140000,  'voluntary' => null],     // ₱1,400
            'MBR-007' => ['compulsory' => 115000,  'voluntary' => null],     // ₱1,150
            'MBR-008' => ['compulsory' => 68000,   'voluntary' => 120000],   // ₱680 / ₱1,200
            'MBR-009' => ['compulsory' => 310000,  'voluntary' => null],     // ₱3,100
            'MBR-010' => ['compulsory' => 95000,   'voluntary' => null],     // ₱950 (inactive)
        ];

        $acctSeq = 1;
        $txSeq   = 1;

        foreach ($members as $code => $balances) {
            $customer = Customer::where('code', $code)->where('store_id', $store->id)->first();

            // ── Compulsory Savings ───────────────────────────────────────────
            $compBalance   = $balances['compulsory'];
            $compAcctNum   = 'SVA-COMP-' . str_pad($acctSeq, 6, '0', STR_PAD_LEFT);
            $compStatus    = $code === 'MBR-010' ? 'dormant' : 'active';
            $openedDate    = '2018-06-01';

            $compAcctId = DB::table('member_savings_accounts')->insertGetId([
                'uuid'                 => (string) Str::uuid(),
                'store_id'             => $store->id,
                'customer_id'          => $customer->id,
                'account_number'       => $compAcctNum,
                'savings_type'         => 'compulsory',
                'current_balance'      => $compBalance,
                'minimum_balance'      => 0,
                'interest_rate'        => 0.020000,  // 2% per annum
                'total_deposited'      => (int)($compBalance * 1.05), // gross deposits
                'total_withdrawn'      => (int)($compBalance * 0.05), // some withdrawals
                'total_interest_earned' => (int)($compBalance * 0.04),
                'status'               => $compStatus,
                'opened_date'          => $openedDate,
                'closed_date'          => null,
                'closed_by'            => null,
                'last_transaction_date' => '2026-01-15',
                'notes'                => 'Compulsory savings — ₱500 deducted monthly from share capital or cash.',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            $acctSeq++;

            // Seed a few recent transactions for compulsory savings
            $this->seedCompulsoryTransactions($store, $customer, $compAcctId, $cashier, $txSeq, $compBalance);

            // ── Voluntary Savings (optional) ─────────────────────────────────
            if ($balances['voluntary'] !== null) {
                $volBalance  = $balances['voluntary'];
                $volAcctNum  = 'SVA-VOL-' . str_pad($acctSeq, 6, '0', STR_PAD_LEFT);

                $volAcctId = DB::table('member_savings_accounts')->insertGetId([
                    'uuid'                 => (string) Str::uuid(),
                    'store_id'             => $store->id,
                    'customer_id'          => $customer->id,
                    'account_number'       => $volAcctNum,
                    'savings_type'         => 'voluntary',
                    'current_balance'      => $volBalance,
                    'minimum_balance'      => 50000,   // ₱500 maintaining balance
                    'interest_rate'        => 0.030000, // 3% per annum
                    'total_deposited'      => (int)($volBalance * 1.12),
                    'total_withdrawn'      => (int)($volBalance * 0.12),
                    'total_interest_earned' => (int)($volBalance * 0.06),
                    'status'               => 'active',
                    'opened_date'          => '2019-01-10',
                    'closed_date'          => null,
                    'closed_by'            => null,
                    'last_transaction_date' => '2026-02-05',
                    'notes'                => 'Voluntary savings account.',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $acctSeq++;

                // Seed recent voluntary transactions
                $this->seedVoluntaryTransactions($store, $customer, $volAcctId, $cashier, $txSeq, $volBalance);
            }
        }
    }

    private function seedCompulsoryTransactions(
        $store, $customer, int $acctId, $cashier, int &$txSeq, int $currentBalance
    ): void {
        // Monthly compulsory deductions for Jan & Feb 2026
        $months = [
            ['2026-01-15', 50000],  // ₱500
            ['2026-02-15', 50000],  // ₱500
        ];

        $balance = $currentBalance - 100000; // work backwards from 2 months ago
        foreach ($months as [$date, $amount]) {
            $before = $balance;
            $after  = $balance + $amount;

            DB::table('savings_transactions')->insert([
                'uuid'               => (string) Str::uuid(),
                'store_id'           => $store->id,
                'customer_id'        => $customer->id,
                'savings_account_id' => $acctId,
                'user_id'            => $cashier->id,
                'transaction_number' => 'SVT-2026-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
                'transaction_type'   => 'compulsory_deduction',
                'amount'             => $amount,
                'balance_before'     => $before,
                'balance_after'      => $after,
                'payment_method'     => 'cash',
                'reference_number'   => null,
                'transaction_date'   => $date,
                'notes'              => 'Monthly compulsory savings deduction.',
                'is_reversed'        => false,
                'reversed_at'        => null,
                'reversed_by'        => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $balance = $after;
        }

        // Annual interest credit (2025)
        $interestAmt = (int) round($currentBalance * 0.02);
        DB::table('savings_transactions')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'customer_id'        => $customer->id,
            'savings_account_id' => $acctId,
            'user_id'            => $cashier->id,
            'transaction_number' => 'SVT-2026-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
            'transaction_type'   => 'interest_credit',
            'amount'             => $interestAmt,
            'balance_before'     => $balance,
            'balance_after'      => $balance + $interestAmt,
            'payment_method'     => 'internal_transfer',
            'reference_number'   => null,
            'transaction_date'   => '2025-12-31',
            'notes'              => 'Annual interest credit for 2025 at 2% p.a.',
            'is_reversed'        => false,
            'reversed_at'        => null,
            'reversed_by'        => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function seedVoluntaryTransactions(
        $store, $customer, int $acctId, $cashier, int &$txSeq, int $currentBalance
    ): void {
        $entries = [
            ['2026-01-05', 'deposit',    100000, 'cash'],          // ₱1,000 deposit
            ['2026-01-20', 'withdrawal', 50000,  'cash'],           // ₱500 withdrawal
            ['2026-02-03', 'deposit',    200000, 'gcash'],          // ₱2,000 deposit
        ];

        $balance = $currentBalance - 250000; // reverse to get starting balance
        foreach ($entries as [$date, $type, $amount, $method]) {
            $before = $balance;
            $after  = $type === 'withdrawal' ? $balance - $amount : $balance + $amount;

            DB::table('savings_transactions')->insert([
                'uuid'               => (string) Str::uuid(),
                'store_id'           => $store->id,
                'customer_id'        => $customer->id,
                'savings_account_id' => $acctId,
                'user_id'            => $cashier->id,
                'transaction_number' => 'SVT-2026-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
                'transaction_type'   => $type,
                'amount'             => $amount,
                'balance_before'     => $before,
                'balance_after'      => $after,
                'payment_method'     => $method,
                'reference_number'   => null,
                'transaction_date'   => $date,
                'notes'              => null,
                'is_reversed'        => false,
                'reversed_at'        => null,
                'reversed_by'        => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $balance = $after;
        }
    }
}
