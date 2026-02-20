<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds patronage_refund_batches and patronage_refund_allocations.
 *
 * FY2024 batch — completed (all paid out via savings credit).
 * FY2025 batch — approved, still distributing.
 */
class PatronageRefundSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $manager = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();
        $cashier = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();

        // Member purchases used for allocation calculation (in centavos)
        // Based on total_purchases field on the customer records
        $memberPurchases2024 = [
            'MBR-001' => 650000,   // ₱6,500
            'MBR-002' => 980000,   // ₱9,800
            'MBR-003' => 1540000,  // ₱15,400
            'MBR-004' => 360000,   // ₱3,600
            'MBR-005' => 2840000,  // ₱28,400
            'MBR-006' => 1520000,  // ₱15,200
            'MBR-007' => 720000,   // ₱7,200
            'MBR-008' => 420000,   // ₱4,200
            'MBR-009' => 1120000,  // ₱11,200
            'MBR-010' => 880000,   // ₱8,800 (inactive now but was active in 2024)
        ];

        $memberPurchases2025 = [
            'MBR-001' => 865000,
            'MBR-002' => 1245000,
            'MBR-003' => 2180000,
            'MBR-004' => 540000,
            'MBR-005' => 3860000,
            'MBR-006' => 2140000,
            'MBR-007' => 980000,
            'MBR-008' => 685000,
            'MBR-009' => 1450000,
            // MBR-010 excluded (inactive)
        ];

        // ── FY2024 Batch — Completed ──────────────────────────────────────────
        $total2024     = array_sum($memberPurchases2024); // 11,030,000
        $prRate2024    = 0.050000; // 5% patronage refund rate
        $totalAlloc2024 = (int) round($total2024 * $prRate2024); // 551,500

        $batch2024 = DB::table('patronage_refund_batches')->insertGetId([
            'uuid'                   => (string) Str::uuid(),
            'store_id'               => $store->id,
            'period_label'           => 'FY2024',
            'period_from'            => '2024-01-01',
            'period_to'              => '2024-12-31',
            'computation_method'     => 'rate_based',
            'pr_rate'                => $prRate2024,
            'pr_fund'                => 0,
            'total_member_purchases' => $total2024,
            'total_store_sales'      => 45000000, // ₱450,000 approx total store sales
            'total_allocated'        => $totalAlloc2024,
            'total_distributed'      => $totalAlloc2024,
            'member_count'           => count($memberPurchases2024),
            'status'                 => 'completed',
            'approved_by'            => $manager->id,
            'approved_at'            => '2025-02-15 10:00:00',
            'notes'                  => 'FY2024 patronage refund. 5% of qualifying member purchases. Credited to savings accounts.',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $allocSeq2024 = 1;
        foreach ($memberPurchases2024 as $code => $purchases) {
            $customer   = Customer::where('code', $code)->where('store_id', $store->id)->first();
            $allocation = (int) round($purchases * $prRate2024);
            $pct        = round(($purchases / $total2024) * 100, 6);

            DB::table('patronage_refund_allocations')->insert([
                'uuid'                  => (string) Str::uuid(),
                'store_id'              => $store->id,
                'batch_id'              => $batch2024,
                'customer_id'           => $customer->id,
                'member_purchases'      => $purchases,
                'allocation_percentage' => $pct,
                'allocation_amount'     => $allocation,
                'status'                => 'paid',
                'payment_method'        => 'savings_credit',
                'reference_number'      => null,
                'paid_date'             => '2025-03-01',
                'paid_by'               => $cashier->id,
                'notes'                 => 'Credited to member compulsory savings account.',
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
            $allocSeq2024++;
        }

        // ── FY2025 Batch — Approved / Distributing ───────────────────────────
        $total2025      = array_sum($memberPurchases2025);
        $prRate2025     = 0.050000;
        $totalAlloc2025 = (int) round($total2025 * $prRate2025);
        $paidOut2025    = 0;

        // Mark MBR-001, MBR-002, MBR-005 as paid; rest are still pending
        $paid2025 = ['MBR-001', 'MBR-002', 'MBR-005'];

        $allocations2025 = [];
        foreach ($memberPurchases2025 as $code => $purchases) {
            $allocation          = (int) round($purchases * $prRate2025);
            $allocations2025[]   = [$code, $purchases, $allocation];
            if (in_array($code, $paid2025)) {
                $paidOut2025 += $allocation;
            }
        }

        $batch2025 = DB::table('patronage_refund_batches')->insertGetId([
            'uuid'                   => (string) Str::uuid(),
            'store_id'               => $store->id,
            'period_label'           => 'FY2025',
            'period_from'            => '2025-01-01',
            'period_to'              => '2025-12-31',
            'computation_method'     => 'rate_based',
            'pr_rate'                => $prRate2025,
            'pr_fund'                => 0,
            'total_member_purchases' => $total2025,
            'total_store_sales'      => 58000000, // ₱580,000
            'total_allocated'        => $totalAlloc2025,
            'total_distributed'      => $paidOut2025,
            'member_count'           => count($memberPurchases2025),
            'status'                 => 'distributing',
            'approved_by'            => $manager->id,
            'approved_at'            => '2026-02-01 09:00:00',
            'notes'                  => 'FY2025 patronage refund. Distribution ongoing. Partial credits released.',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        foreach ($allocations2025 as [$code, $purchases, $allocation]) {
            $customer  = Customer::where('code', $code)->where('store_id', $store->id)->first();
            $isPaid    = in_array($code, $paid2025);
            $pct       = round(($purchases / $total2025) * 100, 6);

            DB::table('patronage_refund_allocations')->insert([
                'uuid'                  => (string) Str::uuid(),
                'store_id'              => $store->id,
                'batch_id'              => $batch2025,
                'customer_id'           => $customer->id,
                'member_purchases'      => $purchases,
                'allocation_percentage' => $pct,
                'allocation_amount'     => $allocation,
                'status'                => $isPaid ? 'paid' : 'pending',
                'payment_method'        => $isPaid ? 'savings_credit' : null,
                'reference_number'      => null,
                'paid_date'             => $isPaid ? '2026-02-10' : null,
                'paid_by'               => $isPaid ? $cashier->id : null,
                'notes'                 => $isPaid ? 'Credited to savings account.' : null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }
}
