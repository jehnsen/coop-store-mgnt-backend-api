<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds loan_products, loans, loan_amortization_schedules, loan_payments.
 *
 * Pre-computed amortization (diminishing balance):
 *  Formula: A = P × r / (1 − (1+r)^−n)
 *  All monetary values in CENTAVOS.
 *
 * Loans seeded:
 *  LN-2024-000001 – CLOSED agricultural loan (Erlinda Pascual)   ₱20,000 / 1% / 6 mo
 *  LN-2025-000001 – ACTIVE agricultural loan (Bernardo Macaraeg) ₱30,000 / 1% / 6 mo
 *  LN-2026-000001 – ACTIVE emergency loan   (Aurelio Dizon)      ₱10,000 / 1.5% / 3 mo
 *  LN-2026-000002 – PENDING livelihood loan (Merced Domingo)     ₱15,000 pending
 */
class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $store      = Store::first();
        $officer    = User::where('email', 'arsenio.valdez@snlsimpc.coop')->first();
        $manager    = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();
        $cashier    = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();

        // ── Loan Products ─────────────────────────────────────────────────────
        $agriProduct = DB::table('loan_products')->insertGetId([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'AGRI-01',
            'name'               => 'Agricultural Loan',
            'description'        => 'For crop production inputs: seeds, fertilizers, pesticides. Payable after harvest.',
            'loan_type'          => 'agricultural',
            'interest_rate'      => 0.0100,   // 1.0% per month
            'interest_method'    => 'diminishing_balance',
            'max_term_months'    => 6,
            'min_amount'         => 500000,   // ₱5,000
            'max_amount'         => 5000000,  // ₱50,000
            'processing_fee_rate' => 0.0100,  // 1%
            'service_fee'        => 0,
            'requires_collateral' => false,
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $emergencyProduct = DB::table('loan_products')->insertGetId([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'EMRG-01',
            'name'               => 'Emergency Loan',
            'description'        => 'Short-term loan for unexpected expenses: medical, calamity, school fees.',
            'loan_type'          => 'emergency',
            'interest_rate'      => 0.0150,   // 1.5% per month
            'interest_method'    => 'diminishing_balance',
            'max_term_months'    => 3,
            'min_amount'         => 100000,   // ₱1,000
            'max_amount'         => 1500000,  // ₱15,000
            'processing_fee_rate' => 0.0100,
            'service_fee'        => 0,
            'requires_collateral' => false,
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $livelihoodProduct = DB::table('loan_products')->insertGetId([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'LVHD-01',
            'name'               => 'Livelihood Loan',
            'description'        => 'For micro-enterprise or livelihood projects: sari-sari store, poultry, hog raising.',
            'loan_type'          => 'livelihood',
            'interest_rate'      => 0.0100,   // 1.0% per month
            'interest_method'    => 'diminishing_balance',
            'max_term_months'    => 12,
            'min_amount'         => 500000,   // ₱5,000
            'max_amount'         => 3000000,  // ₱30,000
            'processing_fee_rate' => 0.0100,
            'service_fee'        => 0,
            'requires_collateral' => false,
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::table('loan_products')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'TERM-01',
            'name'               => 'Term Loan (with Collateral)',
            'description'        => 'Medium-term loan for larger capital needs. Requires land title or equipment as collateral.',
            'loan_type'          => 'term',
            'interest_rate'      => 0.0075,   // 0.75% per month
            'interest_method'    => 'diminishing_balance',
            'max_term_months'    => 24,
            'min_amount'         => 5000000,  // ₱50,000
            'max_amount'         => 20000000, // ₱200,000
            'processing_fee_rate' => 0.0150,
            'service_fee'        => 50000,    // ₱500 fixed
            'requires_collateral' => true,
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        DB::table('loan_products')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'SAL-01',
            'name'               => 'Salary/Deduction Loan',
            'description'        => 'For employed members; repayment via salary deduction through employer.',
            'loan_type'          => 'salary',
            'interest_rate'      => 0.0100,
            'interest_method'    => 'diminishing_balance',
            'max_term_months'    => 12,
            'min_amount'         => 100000,   // ₱1,000
            'max_amount'         => 2000000,  // ₱20,000
            'processing_fee_rate' => 0.0100,
            'service_fee'        => 0,
            'requires_collateral' => false,
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ── LOAN 1: CLOSED — Erlinda Pascual (MBR-002) ───────────────────────
        // P=2,000,000, r=0.01, n=6
        // A = 2,000,000 × 0.01 / (1 − 1.01^−6) = 20,000 / 0.057955 = 344,815
        $erlinda       = Customer::where('code', 'MBR-002')->where('store_id', $store->id)->first();
        $l1Principal   = 2000000;
        $l1Rate        = 0.01;
        $l1ProcFee     = (int) round($l1Principal * 0.01); // ₱200 (20,000 centavos)
        $l1NetProceeds = $l1Principal - $l1ProcFee;        // 1,980,000
        $l1Amort       = 344815; // per period in centavos

        // Schedule (all paid)
        $l1Schedule = [
            // [due_date, beg_bal, principal_due, interest_due, total_due, end_bal]
            ['2024-07-15', 2000000, 324815, 20000, 344815, 1675185],
            ['2024-08-15', 1675185, 328063, 16752, 344815, 1347122],
            ['2024-09-15', 1347122, 331344, 13471, 344815, 1015778],
            ['2024-10-15', 1015778, 334657, 10158, 344815,  681121],
            ['2024-11-15',  681121, 338004,  6811, 344815,  343117],
            ['2024-12-15',  343117, 343117,  3431, 346548,       0], // last adjusted
        ];
        $l1TotalInterest  = 20000 + 16752 + 13471 + 10158 + 6811 + 3431;  // 70,623
        $l1TotalPayable   = $l1Principal + $l1TotalInterest;               // 2,070,623

        $loan1 = DB::table('loans')->insertGetId([
            'uuid'                      => (string) Str::uuid(),
            'store_id'                  => $store->id,
            'loan_number'               => 'LN-2024-000001',
            'customer_id'               => $erlinda->id,
            'loan_product_id'           => $agriProduct,
            'user_id'                   => $officer->id,
            'approved_by'               => $manager->id,
            'disbursed_by'              => $cashier->id,
            'principal_amount'          => $l1Principal,
            'interest_rate'             => $l1Rate,
            'interest_method'           => 'diminishing_balance',
            'term_months'               => 6,
            'payment_interval'          => 'monthly',
            'purpose'                   => 'Purchase of urea, complete fertilizer, and certified rice seeds for wet season 2024.',
            'collateral_description'    => null,
            'processing_fee'            => $l1ProcFee,
            'service_fee'               => 0,
            'net_proceeds'              => $l1NetProceeds,
            'total_interest'            => $l1TotalInterest,
            'total_payable'             => $l1TotalPayable,
            'amortization_amount'       => $l1Amort,
            'outstanding_balance'       => 0,
            'total_principal_paid'      => $l1Principal,
            'total_interest_paid'       => $l1TotalInterest,
            'total_penalty_paid'        => 0,
            'total_penalties_outstanding' => 0,
            'application_date'          => '2024-06-01',
            'approval_date'             => '2024-06-05',
            'disbursement_date'         => '2024-06-15',
            'first_payment_date'        => '2024-07-15',
            'maturity_date'             => '2024-12-15',
            'status'                    => 'closed',
            'rejection_reason'          => null,
            'restructured_from_loan_id' => null,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $paySeq = 1;
        foreach ($l1Schedule as $i => [$dueDate, $begBal, $prinDue, $intDue, $totalDue, $endBal]) {
            $schedId = DB::table('loan_amortization_schedules')->insertGetId([
                'loan_id'           => $loan1,
                'payment_number'    => $i + 1,
                'due_date'          => $dueDate,
                'beginning_balance' => $begBal,
                'principal_due'     => $prinDue,
                'interest_due'      => $intDue,
                'total_due'         => $totalDue,
                'principal_paid'    => $prinDue,
                'interest_paid'     => $intDue,
                'penalty_paid'      => 0,
                'total_paid'        => $totalDue,
                'ending_balance'    => $endBal,
                'paid_date'         => $dueDate,
                'status'            => 'paid',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            DB::table('loan_payments')->insert([
                'uuid'               => (string) Str::uuid(),
                'store_id'           => $store->id,
                'loan_id'            => $loan1,
                'customer_id'        => $erlinda->id,
                'user_id'            => $cashier->id,
                'payment_number'     => 'LP-2024-' . str_pad($paySeq++, 6, '0', STR_PAD_LEFT),
                'amount'             => $totalDue,
                'principal_portion'  => $prinDue,
                'interest_portion'   => $intDue,
                'penalty_portion'    => 0,
                'balance_before'     => $begBal,
                'balance_after'      => $endBal,
                'payment_method'     => 'cash',
                'reference_number'   => null,
                'payment_date'       => $dueDate,
                'notes'              => 'Monthly amortization payment #' . ($i + 1) . '.',
                'is_reversed'        => false,
                'reversed_at'        => null,
                'reversed_by'        => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // ── LOAN 2: ACTIVE — Bernardo Macaraeg (MBR-001) ─────────────────────
        // P=3,000,000, r=0.01, n=6; 3 paid, 3 pending
        // A = 3,000,000 × 0.01 / (1 − 1.01^−6) = 30,000 / 0.057955 = 517,216
        $bernardo      = Customer::where('code', 'MBR-001')->where('store_id', $store->id)->first();
        $l2Principal   = 3000000;
        $l2Amort       = 517216;

        $l2Schedule = [
            // [due_date, beg_bal, principal_due, interest_due, total_due, end_bal, status]
            ['2025-12-15', 3000000,  487216, 30000, 517216, 2512784, 'paid'],
            ['2026-01-15', 2512784,  492088, 25128, 517216, 2020696, 'paid'],
            ['2026-02-15', 2020696,  497009, 20207, 517216, 1523687, 'paid'],
            ['2026-03-15', 1523687,  501979, 15237, 517216, 1021708, 'pending'],
            ['2026-04-15', 1021708,  507001, 10217, 517216,  514707, 'pending'],
            ['2026-05-15',  514707,  514707,  5147, 519854,       0, 'pending'],
        ];
        $l2TotalInterest     = 30000 + 25128 + 20207 + 15237 + 10217 + 5147; // 105,936
        $l2TotalPayable      = $l2Principal + $l2TotalInterest;               // 3,105,936
        $l2PrinPaid          = 487216 + 492088 + 497009;                      // 1,476,313
        $l2IntPaid           = 30000 + 25128 + 20207;                         // 75,335
        $l2OutstandingBal    = 1523687;
        $l2ProcFee           = (int) round($l2Principal * 0.01);              // 30,000

        $loan2 = DB::table('loans')->insertGetId([
            'uuid'                      => (string) Str::uuid(),
            'store_id'                  => $store->id,
            'loan_number'               => 'LN-2025-000001',
            'customer_id'               => $bernardo->id,
            'loan_product_id'           => $agriProduct,
            'user_id'                   => $officer->id,
            'approved_by'               => $manager->id,
            'disbursed_by'              => $cashier->id,
            'principal_amount'          => $l2Principal,
            'interest_rate'             => 0.0100,
            'interest_method'           => 'diminishing_balance',
            'term_months'               => 6,
            'payment_interval'          => 'monthly',
            'purpose'                   => 'Seed, fertilizer, and pesticide inputs for dry season 2025–2026 rice crop on 3 ha farm.',
            'collateral_description'    => null,
            'processing_fee'            => $l2ProcFee,
            'service_fee'               => 0,
            'net_proceeds'              => $l2Principal - $l2ProcFee,
            'total_interest'            => $l2TotalInterest,
            'total_payable'             => $l2TotalPayable,
            'amortization_amount'       => $l2Amort,
            'outstanding_balance'       => $l2OutstandingBal,
            'total_principal_paid'      => $l2PrinPaid,
            'total_interest_paid'       => $l2IntPaid,
            'total_penalty_paid'        => 0,
            'total_penalties_outstanding' => 0,
            'application_date'          => '2025-11-01',
            'approval_date'             => '2025-11-05',
            'disbursement_date'         => '2025-11-15',
            'first_payment_date'        => '2025-12-15',
            'maturity_date'             => '2026-05-15',
            'status'                    => 'active',
            'rejection_reason'          => null,
            'restructured_from_loan_id' => null,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        foreach ($l2Schedule as $i => [$dueDate, $begBal, $prinDue, $intDue, $totalDue, $endBal, $status]) {
            $isPaid     = $status === 'paid';
            DB::table('loan_amortization_schedules')->insertGetId([
                'loan_id'           => $loan2,
                'payment_number'    => $i + 1,
                'due_date'          => $dueDate,
                'beginning_balance' => $begBal,
                'principal_due'     => $prinDue,
                'interest_due'      => $intDue,
                'total_due'         => $totalDue,
                'principal_paid'    => $isPaid ? $prinDue : 0,
                'interest_paid'     => $isPaid ? $intDue : 0,
                'penalty_paid'      => 0,
                'total_paid'        => $isPaid ? $totalDue : 0,
                'ending_balance'    => $endBal,
                'paid_date'         => $isPaid ? $dueDate : null,
                'status'            => $status,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            if ($isPaid) {
                DB::table('loan_payments')->insert([
                    'uuid'               => (string) Str::uuid(),
                    'store_id'           => $store->id,
                    'loan_id'            => $loan2,
                    'customer_id'        => $bernardo->id,
                    'user_id'            => $cashier->id,
                    'payment_number'     => 'LP-' . substr($dueDate, 0, 4) . '-' . str_pad($paySeq++, 6, '0', STR_PAD_LEFT),
                    'amount'             => $totalDue,
                    'principal_portion'  => $prinDue,
                    'interest_portion'   => $intDue,
                    'penalty_portion'    => 0,
                    'balance_before'     => $begBal,
                    'balance_after'      => $endBal,
                    'payment_method'     => 'cash',
                    'reference_number'   => null,
                    'payment_date'       => $dueDate,
                    'notes'              => 'Monthly amortization payment #' . ($i + 1) . '.',
                    'is_reversed'        => false,
                    'reversed_at'        => null,
                    'reversed_by'        => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }

        // ── LOAN 3: ACTIVE — Aurelio Dizon (MBR-005) emergency loan ──────────
        // P=1,000,000, r=0.015, n=3; 1 paid, 2 pending
        // A = 1,000,000 × 0.015 / (1 − 1.015^−3)
        // (1.015)^3 = 1.045678, (1.015)^-3 = 0.955832
        // A = 15,000 / 0.044168 = 339,688
        $aurelio      = Customer::where('code', 'MBR-005')->where('store_id', $store->id)->first();
        $l3Principal  = 1000000;
        $l3Amort      = 339688;
        $l3ProcFee    = (int) round($l3Principal * 0.01); // 10,000

        $l3Schedule = [
            ['2026-02-10', 1000000, 324688, 15000, 339688,  675312, 'paid'],
            ['2026-03-10',  675312, 329608, 10130, 339688,  345704, 'pending'],
            ['2026-04-10',  345704, 345704,  5186, 350890,       0, 'pending'],
        ];
        $l3TotalInterest  = 15000 + 10130 + 5186; // 30,316
        $l3TotalPayable   = $l3Principal + $l3TotalInterest;

        $loan3 = DB::table('loans')->insertGetId([
            'uuid'                      => (string) Str::uuid(),
            'store_id'                  => $store->id,
            'loan_number'               => 'LN-2026-000001',
            'customer_id'               => $aurelio->id,
            'loan_product_id'           => $emergencyProduct,
            'user_id'                   => $officer->id,
            'approved_by'               => $manager->id,
            'disbursed_by'              => $cashier->id,
            'principal_amount'          => $l3Principal,
            'interest_rate'             => 0.0150,
            'interest_method'           => 'diminishing_balance',
            'term_months'               => 3,
            'payment_interval'          => 'monthly',
            'purpose'                   => 'Veterinary expenses — ASF prevention vaccination and biosecurity materials for backyard poultry farm.',
            'collateral_description'    => null,
            'processing_fee'            => $l3ProcFee,
            'service_fee'               => 0,
            'net_proceeds'              => $l3Principal - $l3ProcFee,
            'total_interest'            => $l3TotalInterest,
            'total_payable'             => $l3TotalPayable,
            'amortization_amount'       => $l3Amort,
            'outstanding_balance'       => 675312,
            'total_principal_paid'      => 324688,
            'total_interest_paid'       => 15000,
            'total_penalty_paid'        => 0,
            'total_penalties_outstanding' => 0,
            'application_date'          => '2026-01-05',
            'approval_date'             => '2026-01-08',
            'disbursement_date'         => '2026-01-10',
            'first_payment_date'        => '2026-02-10',
            'maturity_date'             => '2026-04-10',
            'status'                    => 'active',
            'rejection_reason'          => null,
            'restructured_from_loan_id' => null,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        foreach ($l3Schedule as $i => [$dueDate, $begBal, $prinDue, $intDue, $totalDue, $endBal, $status]) {
            $isPaid = $status === 'paid';
            DB::table('loan_amortization_schedules')->insert([
                'loan_id'           => $loan3,
                'payment_number'    => $i + 1,
                'due_date'          => $dueDate,
                'beginning_balance' => $begBal,
                'principal_due'     => $prinDue,
                'interest_due'      => $intDue,
                'total_due'         => $totalDue,
                'principal_paid'    => $isPaid ? $prinDue : 0,
                'interest_paid'     => $isPaid ? $intDue : 0,
                'penalty_paid'      => 0,
                'total_paid'        => $isPaid ? $totalDue : 0,
                'ending_balance'    => $endBal,
                'paid_date'         => $isPaid ? $dueDate : null,
                'status'            => $status,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            if ($isPaid) {
                DB::table('loan_payments')->insert([
                    'uuid'               => (string) Str::uuid(),
                    'store_id'           => $store->id,
                    'loan_id'            => $loan3,
                    'customer_id'        => $aurelio->id,
                    'user_id'            => $cashier->id,
                    'payment_number'     => 'LP-2026-' . str_pad($paySeq++, 6, '0', STR_PAD_LEFT),
                    'amount'             => $totalDue,
                    'principal_portion'  => $prinDue,
                    'interest_portion'   => $intDue,
                    'penalty_portion'    => 0,
                    'balance_before'     => $begBal,
                    'balance_after'      => $endBal,
                    'payment_method'     => 'cash',
                    'reference_number'   => null,
                    'payment_date'       => $dueDate,
                    'notes'              => 'Emergency loan payment #' . ($i + 1) . '.',
                    'is_reversed'        => false,
                    'reversed_at'        => null,
                    'reversed_by'        => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }

        // ── LOAN 4: PENDING — Merced Domingo (MBR-008) livelihood loan ────────
        $merced = Customer::where('code', 'MBR-008')->where('store_id', $store->id)->first();

        DB::table('loans')->insert([
            'uuid'                      => (string) Str::uuid(),
            'store_id'                  => $store->id,
            'loan_number'               => 'LN-2026-000002',
            'customer_id'               => $merced->id,
            'loan_product_id'           => $livelihoodProduct,
            'user_id'                   => $officer->id,
            'approved_by'               => null,
            'disbursed_by'              => null,
            'principal_amount'          => 1500000,  // ₱15,000
            'interest_rate'             => 0.0100,
            'interest_method'           => 'diminishing_balance',
            'term_months'               => 12,
            'payment_interval'          => 'monthly',
            'purpose'                   => 'Sari-sari store restocking — grocery goods, beverages, and personal care items to expand inventory.',
            'collateral_description'    => null,
            'processing_fee'            => 15000,  // ₱150
            'service_fee'               => 0,
            'net_proceeds'              => 1485000,
            'total_interest'            => 0,  // not yet computed (pending approval)
            'total_payable'             => 0,
            'amortization_amount'       => 0,
            'outstanding_balance'       => 0,
            'total_principal_paid'      => 0,
            'total_interest_paid'       => 0,
            'total_penalty_paid'        => 0,
            'total_penalties_outstanding' => 0,
            'application_date'          => '2026-02-10',
            'approval_date'             => null,
            'disbursement_date'         => null,
            'first_payment_date'        => null,
            'maturity_date'             => null,
            'status'                    => 'pending',
            'rejection_reason'          => null,
            'restructured_from_loan_id' => null,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }
}
