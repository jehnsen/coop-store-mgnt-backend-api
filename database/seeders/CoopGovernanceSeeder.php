<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds coop_officers, aga_records, and cda_annual_reports.
 *
 * Officers: 7-person Board of Directors, 3-member Audit Committee,
 *           3-member Election Committee.
 * AGA: 2024 (finalized), 2025 (finalized).
 * CDA Report: 2024 (submitted), 2025 (draft).
 */
class CoopGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $gm      = User::where('email', 'danilo.macaraeg@snlsimpc.coop')->first();
        $manager = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();

        $termFrom2024 = '2024-01-01';
        $termTo2024   = '2025-12-31'; // 2-year terms typical in PH cooperatives

        // ── Board of Directors (currently serving) ────────────────────────────
        $boardMembers = [
            // [name, position, customer_code or null]
            ['Danilo P. Macaraeg',       'Chairperson',           'MBR-005'],
            ['Erlinda R. Pascual',        'Vice-Chairperson',      'MBR-002'],
            ['Rodrigo M. Tolentino',      'Secretary',             'MBR-003'],
            ['Corazon V. Valdez',         'Treasurer',             'MBR-006'],
            ['Bernardo C. Macaraeg',      'Director',              'MBR-001'],
            ['Renato L. Ramos',           'Director',              'MBR-009'],
            ['Feliciano A. Bautista',     'Director',              'MBR-007'],
        ];

        foreach ($boardMembers as [$name, $position, $custCode]) {
            $customer = $custCode
                ? Customer::where('code', $custCode)->where('store_id', $store->id)->first()
                : null;

            DB::table('coop_officers')->insert([
                'uuid'       => (string) Str::uuid(),
                'store_id'   => $store->id,
                'customer_id' => $customer?->id,
                'name'       => $name,
                'position'   => $position,
                'committee'  => 'Board of Directors',
                'term_from'  => $termFrom2024,
                'term_to'    => $termTo2024,
                'is_active'  => true,
                'notes'      => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Audit Committee ──────────────────────────────────────────────────
        $auditMembers = [
            ['Natividad C. Buenaventura', 'Audit Committee Chair',   'MBR-004'],
            ['Aurelio D. Dizon',           'Audit Committee Member',  'MBR-005'],
            ['Merced S. Domingo',          'Audit Committee Member',  'MBR-008'],
        ];

        foreach ($auditMembers as [$name, $position, $custCode]) {
            $customer = Customer::where('code', $custCode)->where('store_id', $store->id)->first();

            DB::table('coop_officers')->insert([
                'uuid'       => (string) Str::uuid(),
                'store_id'   => $store->id,
                'customer_id' => $customer?->id,
                'name'       => $name,
                'position'   => $position,
                'committee'  => 'Audit Committee',
                'term_from'  => $termFrom2024,
                'term_to'    => $termTo2024,
                'is_active'  => true,
                'notes'      => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Election Committee ──────────────────────────────────────────────
        $elecMembers = [
            ['Rosario M. Panganiban',   'Election Committee Chair',   null],
            ['Eduardo T. Mendez',        'Election Committee Member',  null],
            ['Maribel P. Flores',        'Election Committee Member',  null],
        ];

        foreach ($elecMembers as [$name, $position, $custCode]) {
            DB::table('coop_officers')->insert([
                'uuid'       => (string) Str::uuid(),
                'store_id'   => $store->id,
                'customer_id' => null,
                'name'       => $name,
                'position'   => $position,
                'committee'  => 'Election Committee',
                'term_from'  => $termFrom2024,
                'term_to'    => $termTo2024,
                'is_active'  => true,
                'notes'      => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── AGA Records ──────────────────────────────────────────────────────

        // 2024 Annual General Assembly (finalized)
        DB::table('aga_records')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'aga_number'         => 'AGA-2024-01',
            'meeting_type'       => 'annual',
            'meeting_year'       => 2024,
            'meeting_date'       => '2024-02-17',
            'venue'              => 'San Isidro Municipal Gymnasium, Poblacion, San Isidro, Nueva Ecija',
            'total_members'      => 85,
            'members_present'    => 62,
            'members_via_proxy'  => 8,
            'quorum_percentage'  => round((62 + 8) / 85 * 100, 2), // 82.35%
            'quorum_achieved'    => true,
            'presiding_officer'  => 'Danilo P. Macaraeg',
            'secretary'          => 'Rodrigo M. Tolentino',
            'agenda'             => json_encode([
                'Call to Order',
                'Roll Call and Establishment of Quorum',
                'Approval of Previous AGA Minutes',
                'Presentation of Annual Report and Financial Statements (FY2023)',
                'Election of Board of Directors (4 seats)',
                'Election of Audit Committee (2 seats)',
                'Approval of FY2024 Work Plan and Budget',
                'Distribution of Patronage Refunds (FY2023)',
                'Open Forum',
                'Adjournment',
            ]),
            'resolutions_passed' => json_encode([
                'Approved FY2023 financial statements and annual report.',
                'Elected 4 new directors and 2 audit committee members for 2024–2025 term.',
                'Approved FY2024 operational budget of ₱2,850,000.',
                'Approved 5% patronage refund rate for FY2024.',
                'Authorized BOD to explore additional agri-loan products for vegetable farmers.',
            ]),
            'minutes_text'       => 'The 2024 Annual General Assembly of SNLSI MPC was called to order at 9:02 AM by Chairperson Danilo P. Macaraeg. A quorum of 70 members (62 present, 8 via proxy) out of 85 total membership was established. The assembly proceeded with approval of the FY2023 financial statements showing net surplus of ₱485,320. Officers were elected and work plan was approved unanimously.',
            'status'             => 'finalized',
            'finalized_by'       => $manager->id,
            'finalized_at'       => '2024-03-10 14:00:00',
            'notes'              => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // 2025 Annual General Assembly (finalized)
        DB::table('aga_records')->insert([
            'uuid'               => (string) Str::uuid(),
            'store_id'           => $store->id,
            'aga_number'         => 'AGA-2025-01',
            'meeting_type'       => 'annual',
            'meeting_year'       => 2025,
            'meeting_date'       => '2025-02-22',
            'venue'              => 'San Isidro Municipal Gymnasium, Poblacion, San Isidro, Nueva Ecija',
            'total_members'      => 92,
            'members_present'    => 68,
            'members_via_proxy'  => 10,
            'quorum_percentage'  => round((68 + 10) / 92 * 100, 2), // 84.78%
            'quorum_achieved'    => true,
            'presiding_officer'  => 'Danilo P. Macaraeg',
            'secretary'          => 'Rodrigo M. Tolentino',
            'agenda'             => json_encode([
                'Call to Order',
                'Roll Call and Establishment of Quorum',
                'Approval of Previous AGA Minutes (2024)',
                'Presentation of Annual Report and Financial Statements (FY2024)',
                'Election of Board of Directors (3 seats)',
                'Approval of FY2025 Work Plan and Budget',
                'Distribution of Patronage Refunds (FY2024)',
                'Proposed Expansion of Grocery Section',
                'Open Forum',
                'Adjournment',
            ]),
            'resolutions_passed' => json_encode([
                'Approved FY2024 financial statements and annual report.',
                'Re-elected 3 incumbent directors for 2025–2026 term.',
                'Approved FY2025 operational budget of ₱3,450,000.',
                'Approved FY2024 patronage refund at 5% totaling ₱551,500 — credited to savings accounts.',
                'Approved expansion of grocery section at Brgy. Tagumpay satellite store.',
                'Approved opening of new savings product for children of members (Youth Savings).',
            ]),
            'minutes_text'       => 'The 2025 Annual General Assembly of SNLSI MPC was called to order at 9:10 AM. Quorum of 78 members achieved (68 present, 10 via proxy) of 92 total membership. FY2024 net surplus of ₱624,180 was presented and approved. Three directors were re-elected unopposed. The grocery expansion plan was presented by the General Manager and approved by majority vote.',
            'status'             => 'finalized',
            'finalized_by'       => $manager->id,
            'finalized_at'       => '2025-03-15 14:00:00',
            'notes'              => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ── CDA Annual Reports ────────────────────────────────────────────────

        // FY2024 (submitted to CDA)
        DB::table('cda_annual_reports')->insert([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'report_year'          => 2024,
            'period_from'          => '2024-01-01',
            'period_to'            => '2024-12-31',
            'cda_reg_number'       => '9520-0311210',
            'cooperative_type'     => 'Multi-Purpose Cooperative',
            'area_of_operation'    => 'Municipal',
            'report_data'          => json_encode([
                'membership' => [
                    'total_members'         => 85,
                    'new_members'           => 8,
                    'resigned_members'      => 1,
                    'expelled_members'      => 0,
                    'active_members'        => 84,
                    'inactive_members'      => 1,
                ],
                'financial_highlights' => [
                    'total_assets'          => 18500000,   // ₱185,000
                    'total_liabilities'     => 4200000,    // ₱42,000
                    'members_equity'        => 14300000,   // ₱143,000
                    'gross_revenues'        => 9850000,    // ₱98,500
                    'net_surplus'           => 624180,     // ₱6,241.80
                    'share_capital_paid_up' => 6450000,    // ₱64,500
                    'loans_receivable'      => 3120000,    // ₱31,200
                    'savings_deposits'      => 2840000,    // ₱28,400
                ],
                'operations' => [
                    'total_sales'              => 45000000,
                    'total_loan_releases'      => 8500000,
                    'total_loan_collections'   => 7200000,
                    'patronage_refund_amount'  => 551500,
                    'aga_held'                 => true,
                    'aga_date'                 => '2024-02-17',
                    'quorum_percentage'        => 82.35,
                ],
            ]),
            'status'               => 'submitted',
            'compiled_by'          => $manager->id,
            'compiled_at'          => '2025-01-20 09:00:00',
            'finalized_by'         => $gm->id,
            'finalized_at'         => '2025-02-10 14:00:00',
            'submitted_date'       => '2025-03-31',
            'submission_reference' => 'CDA-NE-2025-0381',
            'notes'                => 'Submitted on-time to CDA Region III office in San Fernando, Pampanga.',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // FY2025 (draft — being prepared)
        DB::table('cda_annual_reports')->insert([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'report_year'          => 2025,
            'period_from'          => '2025-01-01',
            'period_to'            => '2025-12-31',
            'cda_reg_number'       => '9520-0311210',
            'cooperative_type'     => 'Multi-Purpose Cooperative',
            'area_of_operation'    => 'Municipal',
            'report_data'          => json_encode([
                'membership' => [
                    'total_members'    => 92,
                    'new_members'      => 7,
                    'resigned_members' => 0,
                    'expelled_members' => 0,
                    'active_members'   => 91,
                    'inactive_members' => 1,
                ],
                'financial_highlights' => [
                    'total_assets'          => 22400000,
                    'total_liabilities'     => 5100000,
                    'members_equity'        => 17300000,
                    'gross_revenues'        => 12600000,
                    'net_surplus'           => 0,           // TBD — audit ongoing
                    'share_capital_paid_up' => 7820000,
                    'loans_receivable'      => 4520000,
                    'savings_deposits'      => 3640000,
                ],
            ]),
            'status'               => 'draft',
            'compiled_by'          => $manager->id,
            'compiled_at'          => '2026-01-25 09:00:00',
            'finalized_by'         => null,
            'finalized_at'         => null,
            'submitted_date'       => null,
            'submission_reference' => null,
            'notes'                => 'FY2025 report in preparation. Awaiting final audit from Audit Committee. Deadline: March 31, 2026.',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }
}
