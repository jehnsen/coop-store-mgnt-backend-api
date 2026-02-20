<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds:
 *  - membership_applications  (1 per active/inactive member + 1 pending new applicant)
 *  - membership_fees          (admission fees + annual dues)
 * Also patches customers.is_member, customers.member_id, customers.accumulated_patronage.
 */
class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $manager = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();
        $cashier = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();

        // ── Active & inactive members (codes MBR-001..010) ────────────────────
        // Each has an approved application. We seed it chronologically;
        // members joined in different years to simulate a maturing cooperative.
        $memberApps = [
            // code, joined_year, civil_status, occupation, monthly_income
            ['MBR-001', 2018, 'married',   'Farmer',             '10,001 – 20,000'],
            ['MBR-002', 2019, 'married',   'Farmer',             '10,001 – 20,000'],
            ['MBR-003', 2017, 'married',   'Farmer',             '20,001 – 30,000'],
            ['MBR-004', 2020, 'widowed',   'Vegetable Farmer',   'Below 10,000'],
            ['MBR-005', 2016, 'married',   'Poultry Raiser',     '20,001 – 30,000'],
            ['MBR-006', 2021, 'married',   'Livestock Raiser',   '10,001 – 20,000'],
            ['MBR-007', 2019, 'single',    'Fishpond Operator',  'Below 10,000'],
            ['MBR-008', 2022, 'married',   'Store Owner',        '10,001 – 20,000'],
            ['MBR-009', 2018, 'married',   'Rice Trader',        '20,001 – 30,000'],
            ['MBR-010', 2020, 'single',    'Farmer',             'Below 10,000'],
        ];

        $appNum  = 1;
        $feeNum  = 1;

        foreach ($memberApps as [$code, $year, $civil, $occupation, $income]) {
            $customer = Customer::where('code', $code)->where('store_id', $store->id)->first();

            $appDate    = now()->setDate($year, 3, 15)->startOfDay();
            $reviewDate = (clone $appDate)->addDays(7);
            $appNumber  = 'APP-' . $year . '-' . str_pad($appNum++, 6, '0', STR_PAD_LEFT);

            // Admission fee: ₱200 (20,000 centavos)
            $admissionFee = 20000;

            $application = DB::table('membership_applications')->insertGetId([
                'uuid'                 => (string) Str::uuid(),
                'store_id'             => $store->id,
                'customer_id'          => $customer->id,
                'application_number'   => $appNumber,
                'application_type'     => 'new',
                'application_date'     => $appDate->toDateString(),
                'civil_status'         => $civil,
                'occupation'           => $occupation,
                'monthly_income_range' => $income,
                'admission_fee_amount' => $admissionFee,
                'status'               => 'approved',
                'reviewed_by'          => $manager->id,
                'reviewed_at'          => $reviewDate->toDateTimeString(),
                'notes'                => null,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // Admission fee record
            DB::table('membership_fees')->insert([
                'uuid'             => (string) Str::uuid(),
                'store_id'         => $store->id,
                'customer_id'      => $customer->id,
                'user_id'          => $cashier->id,
                'application_id'   => $application,
                'fee_number'       => 'MFE-' . $year . '-' . str_pad($feeNum++, 6, '0', STR_PAD_LEFT),
                'fee_type'         => 'admission_fee',
                'amount'           => $admissionFee,
                'payment_method'   => 'cash',
                'reference_number' => null,
                'transaction_date' => $reviewDate->toDateString(),
                'period_year'      => null,
                'notes'            => 'Admission fee upon membership approval.',
                'is_reversed'      => false,
                'reversed_at'      => null,
                'reversed_by'      => null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Annual dues: ₱100 (10,000 centavos) per year from join year to 2025
            $annualDue = 10000;
            for ($y = $year + 1; $y <= 2025; $y++) {
                DB::table('membership_fees')->insert([
                    'uuid'             => (string) Str::uuid(),
                    'store_id'         => $store->id,
                    'customer_id'      => $customer->id,
                    'user_id'          => $cashier->id,
                    'application_id'   => null,
                    'fee_number'       => 'MFE-' . $y . '-' . str_pad($feeNum++, 6, '0', STR_PAD_LEFT),
                    'fee_type'         => 'annual_dues',
                    'amount'           => $annualDue,
                    'payment_method'   => 'cash',
                    'reference_number' => null,
                    'transaction_date' => $y . '-01-15',
                    'period_year'      => $y,
                    'notes'            => 'Annual membership dues for ' . $y . '.',
                    'is_reversed'      => false,
                    'reversed_at'      => null,
                    'reversed_by'      => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            // Patch customer: mark as member + assign member_id
            $memberNumber = $year . '-' . str_pad(substr($code, 4), 4, '0', STR_PAD_LEFT);
            DB::table('customers')
                ->where('id', $customer->id)
                ->update([
                    'is_member'   => $code !== 'MBR-010', // MBR-010 is inactive, still a member
                    'member_id'   => 'SNLSI-MBR-' . $memberNumber,
                    'updated_at'  => now(),
                ]);
        }

        // ── Pending new applicant (walk-in prospective member) ─────────────────
        // This person doesn't exist in customers yet — add them first
        $newApplicant = Customer::create([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'code'               => 'MBR-011',
            'name'               => 'Ligaya Sicat',
            'type'               => 'individual',
            'company_name'       => null,
            'tin'                => null,
            'email'              => null,
            'phone'              => null,
            'mobile'             => '0927-234-5678',
            'address'            => 'Purok 2, Barangay San Jose',
            'city'               => 'San Isidro',
            'province'           => 'Nueva Ecija',
            'postal_code'        => '3112',
            'member_status'      => 'applicant',
            'credit_limit'       => 0,
            'credit_terms_days'  => 0,
            'total_outstanding'  => 0,
            'total_purchases'    => 0,
            'payment_rating'     => 'excellent',
            'notes'              => 'Pending membership application. Vegetable grower from Brgy. San Jose.',
            'is_active'          => true,
            'allow_credit'       => false,
        ]);

        DB::table('membership_applications')->insert([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'customer_id'          => $newApplicant->id,
            'application_number'   => 'APP-2026-' . str_pad($appNum, 6, '0', STR_PAD_LEFT),
            'application_type'     => 'new',
            'application_date'     => '2026-02-10',
            'civil_status'         => 'married',
            'occupation'           => 'Vegetable Farmer',
            'monthly_income_range' => 'Below 10,000',
            'admission_fee_amount' => 20000,
            'status'               => 'pending',
            'reviewed_by'          => null,
            'reviewed_at'          => null,
            'notes'                => 'For review at next BOD meeting on March 5, 2026.',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }
}
