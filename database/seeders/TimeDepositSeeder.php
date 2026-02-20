<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds time_deposits and time_deposit_transactions.
 *
 * Two active placements and one matured/rolled-over placement — typical for a
 * rural cooperative where a handful of members park harvest proceeds as TD.
 */
class TimeDepositSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $cashier = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();
        $manager = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();

        $txSeq = 1;

        // ── TD-1: Erlinda Pascual (MBR-002) — Active 12-month TD ─────────────
        // Placed: 2025-03-01, Matures: 2026-03-01
        // Principal: ₱50,000 (5,000,000 centavos), 6% p.a. simple on maturity
        // Expected interest = 5,000,000 × 0.06 × 1 = 300,000 centavos (₱3,000)
        $erlinda     = Customer::where('code', 'MBR-002')->where('store_id', $store->id)->first();
        $td1Prin     = 5000000;
        $td1Rate     = 0.060000;
        $td1Months   = 12;
        $td1Placed   = '2025-03-01';
        $td1Matures  = '2026-03-01';
        $td1ExpInt   = (int) round($td1Prin * $td1Rate * ($td1Months / 12)); // 300,000

        $td1Id = DB::table('time_deposits')->insertGetId([
            'uuid'                        => (string) Str::uuid(),
            'store_id'                    => $store->id,
            'customer_id'                 => $erlinda->id,
            'account_number'              => 'TD-2025-000001',
            'principal_amount'            => $td1Prin,
            'interest_rate'               => $td1Rate,
            'interest_method'             => 'simple_on_maturity',
            'payment_frequency'           => 'on_maturity',
            'term_months'                 => $td1Months,
            'early_withdrawal_penalty_rate' => 0.2500,
            'placement_date'              => $td1Placed,
            'maturity_date'               => $td1Matures,
            'current_balance'             => $td1Prin,   // no accrual yet (paid on maturity)
            'total_interest_earned'       => 0,
            'expected_interest'           => $td1ExpInt,
            'status'                      => 'active',
            'matured_at'                  => null,
            'pre_terminated_at'           => null,
            'pre_terminated_by'           => null,
            'pre_termination_reason'      => null,
            'rollover_count'              => 0,
            'parent_time_deposit_id'      => null,
            'notes'                       => 'Harvest proceeds placed after 2024 dry-season harvest.',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // Placement transaction
        DB::table('time_deposit_transactions')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'customer_id'        => $erlinda->id,
            'time_deposit_id'    => $td1Id,
            'user_id'            => $cashier->id,
            'transaction_number' => 'TDT-2025-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
            'transaction_type'   => 'placement',
            'amount'             => $td1Prin,
            'interest_amount'    => 0,
            'penalty_amount'     => 0,
            'balance_before'     => 0,
            'balance_after'      => $td1Prin,
            'payment_method'     => 'cash',
            'reference_number'   => null,
            'transaction_date'   => $td1Placed,
            'period_from'        => null,
            'period_to'          => null,
            'notes'              => 'Initial placement — harvest proceeds.',
            'is_reversed'        => false,
            'reversed_at'        => null,
            'reversed_by'        => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ── TD-2: Aurelio Dizon (MBR-005) — Active 6-month TD ───────────────
        // Placed: 2025-09-01, Matures: 2026-03-01
        // Principal: ₱30,000 (3,000,000 centavos), 5% p.a. simple on maturity
        // Expected interest = 3,000,000 × 0.05 × 0.5 = 75,000 centavos (₱750)
        $aurelio    = Customer::where('code', 'MBR-005')->where('store_id', $store->id)->first();
        $td2Prin    = 3000000;
        $td2Rate    = 0.050000;
        $td2Months  = 6;
        $td2ExpInt  = (int) round($td2Prin * $td2Rate * ($td2Months / 12)); // 75,000

        $td2Id = DB::table('time_deposits')->insertGetId([
            'uuid'                        => (string) Str::uuid(),
            'store_id'                    => $store->id,
            'customer_id'                 => $aurelio->id,
            'account_number'              => 'TD-2025-000002',
            'principal_amount'            => $td2Prin,
            'interest_rate'               => $td2Rate,
            'interest_method'             => 'simple_on_maturity',
            'payment_frequency'           => 'on_maturity',
            'term_months'                 => $td2Months,
            'early_withdrawal_penalty_rate' => 0.2500,
            'placement_date'              => '2025-09-01',
            'maturity_date'               => '2026-03-01',
            'current_balance'             => $td2Prin,
            'total_interest_earned'       => 0,
            'expected_interest'           => $td2ExpInt,
            'status'                      => 'active',
            'matured_at'                  => null,
            'pre_terminated_at'           => null,
            'pre_terminated_by'           => null,
            'pre_termination_reason'      => null,
            'rollover_count'              => 0,
            'parent_time_deposit_id'      => null,
            'notes'                       => 'Poultry farm savings placed as time deposit.',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        DB::table('time_deposit_transactions')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'customer_id'        => $aurelio->id,
            'time_deposit_id'    => $td2Id,
            'user_id'            => $cashier->id,
            'transaction_number' => 'TDT-2025-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
            'transaction_type'   => 'placement',
            'amount'             => $td2Prin,
            'interest_amount'    => 0,
            'penalty_amount'     => 0,
            'balance_before'     => 0,
            'balance_after'      => $td2Prin,
            'payment_method'     => 'cash',
            'reference_number'   => null,
            'transaction_date'   => '2025-09-01',
            'period_from'        => null,
            'period_to'          => null,
            'notes'              => 'Placement from poultry farm earnings.',
            'is_reversed'        => false,
            'reversed_at'        => null,
            'reversed_by'        => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ── TD-3: Bernardo Macaraeg (MBR-001) — Matured TD (rolled over) ────
        // Parent: 2024-03-01 to 2025-03-01, ₱20,000, 6% p.a.
        // Expected interest: 20,000 × 0.06 × 1 = ₱1,200
        // Rolled over into a new 12-month TD starting 2025-03-01
        $bernardo    = Customer::where('code', 'MBR-001')->where('store_id', $store->id)->first();
        $td3ParentPrin = 2000000;
        $td3ParentInt  = (int) round($td3ParentPrin * 0.06); // 120,000

        // Parent TD (rolled_over)
        $td3ParentId = DB::table('time_deposits')->insertGetId([
            'uuid'                        => (string) Str::uuid(),
            'store_id'                    => $store->id,
            'customer_id'                 => $bernardo->id,
            'account_number'              => 'TD-2024-000001',
            'principal_amount'            => $td3ParentPrin,
            'interest_rate'               => 0.060000,
            'interest_method'             => 'simple_on_maturity',
            'payment_frequency'           => 'on_maturity',
            'term_months'                 => 12,
            'early_withdrawal_penalty_rate' => 0.2500,
            'placement_date'              => '2024-03-01',
            'maturity_date'               => '2025-03-01',
            'current_balance'             => $td3ParentPrin + $td3ParentInt,
            'total_interest_earned'       => $td3ParentInt,
            'expected_interest'           => $td3ParentInt,
            'status'                      => 'rolled_over',
            'matured_at'                  => '2025-03-01 09:00:00',
            'pre_terminated_at'           => null,
            'pre_terminated_by'           => null,
            'pre_termination_reason'      => null,
            'rollover_count'              => 0,
            'parent_time_deposit_id'      => null,
            'notes'                       => 'Harvest savings — rolled over on maturity.',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // Maturity payout transaction (rolled over — internally transferred)
        DB::table('time_deposit_transactions')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'customer_id'        => $bernardo->id,
            'time_deposit_id'    => $td3ParentId,
            'user_id'            => $manager->id,
            'transaction_number' => 'TDT-2025-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
            'transaction_type'   => 'rollover',
            'amount'             => $td3ParentPrin + $td3ParentInt,
            'interest_amount'    => $td3ParentInt,
            'penalty_amount'     => 0,
            'balance_before'     => $td3ParentPrin,
            'balance_after'      => 0,
            'payment_method'     => 'internal_transfer',
            'reference_number'   => null,
            'transaction_date'   => '2025-03-01',
            'period_from'        => '2024-03-01',
            'period_to'          => '2025-03-01',
            'notes'              => 'Rollover into TD-2025-000003. Interest ₱1,200 added to principal.',
            'is_reversed'        => false,
            'reversed_at'        => null,
            'reversed_by'        => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Child TD — new 12-month placement including rolled interest
        $td3ChildPrin  = $td3ParentPrin + $td3ParentInt; // ₱21,200 → 2,120,000
        $td3ChildExpInt = (int) round($td3ChildPrin * 0.06); // 127,200

        $td3ChildId = DB::table('time_deposits')->insertGetId([
            'uuid'                        => (string) Str::uuid(),
            'store_id'                    => $store->id,
            'customer_id'                 => $bernardo->id,
            'account_number'              => 'TD-2025-000003',
            'principal_amount'            => $td3ChildPrin,
            'interest_rate'               => 0.060000,
            'interest_method'             => 'simple_on_maturity',
            'payment_frequency'           => 'on_maturity',
            'term_months'                 => 12,
            'early_withdrawal_penalty_rate' => 0.2500,
            'placement_date'              => '2025-03-01',
            'maturity_date'               => '2026-03-01',
            'current_balance'             => $td3ChildPrin,
            'total_interest_earned'       => 0,
            'expected_interest'           => $td3ChildExpInt,
            'status'                      => 'active',
            'matured_at'                  => null,
            'pre_terminated_at'           => null,
            'pre_terminated_by'           => null,
            'pre_termination_reason'      => null,
            'rollover_count'              => 1,
            'parent_time_deposit_id'      => $td3ParentId,
            'notes'                       => 'Rollover from TD-2024-000001. Principal includes ₱1,200 interest.',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // Placement transaction for rolled-over TD
        DB::table('time_deposit_transactions')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'customer_id'        => $bernardo->id,
            'time_deposit_id'    => $td3ChildId,
            'user_id'            => $manager->id,
            'transaction_number' => 'TDT-2025-' . str_pad($txSeq++, 6, '0', STR_PAD_LEFT),
            'transaction_type'   => 'placement',
            'amount'             => $td3ChildPrin,
            'interest_amount'    => 0,
            'penalty_amount'     => 0,
            'balance_before'     => 0,
            'balance_after'      => $td3ChildPrin,
            'payment_method'     => 'internal_transfer',
            'reference_number'   => null,
            'transaction_date'   => '2025-03-01',
            'period_from'        => null,
            'period_to'          => null,
            'notes'              => 'Rolled-over from TD-2024-000001.',
            'is_reversed'        => false,
            'reversed_at'        => null,
            'reversed_by'        => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }
}
