<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Mutual Aid Fund module:
 *   maf_programs       – benefit program configurations
 *   maf_beneficiaries  – named beneficiaries for all active members
 *   maf_contributions  – monthly MAF contributions (Jan–Feb 2026 sample)
 *   maf_claims         – 1 paid hospitalization claim, 1 pending death claim
 *   maf_claim_payments – disbursement for the paid claim
 */
class MafSeeder extends Seeder
{
    public function run(): void
    {
        $store   = Store::first();
        $cashier = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();
        $manager = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();
        $gm      = User::where('email', 'danilo.macaraeg@snlsimpc.coop')->first();

        // ── MAF Programs ──────────────────────────────────────────────────────
        $deathProg = DB::table('maf_programs')->insertGetId([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'code'                 => 'DEATH-01',
            'name'                 => 'Death Benefit',
            'description'          => 'Lump-sum benefit paid to designated beneficiary upon death of an active member.',
            'benefit_type'         => 'death',
            'benefit_amount'       => 1500000,   // ₱15,000
            'waiting_period_days'  => 180,        // 6 months membership before eligible
            'max_claims_per_year'  => 1,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $hospProg = DB::table('maf_programs')->insertGetId([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'code'                 => 'HOSP-01',
            'name'                 => 'Hospitalization Benefit',
            'description'          => 'Financial assistance for hospital confinement of 3 or more days. One claim per year.',
            'benefit_type'         => 'hospitalization',
            'benefit_amount'       => 500000,    // ₱5,000
            'waiting_period_days'  => 90,
            'max_claims_per_year'  => 1,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $disabilityProg = DB::table('maf_programs')->insertGetId([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'code'                 => 'DISB-01',
            'name'                 => 'Total & Permanent Disability Benefit',
            'description'          => 'Benefit for members who suffer total and permanent disability and are unable to work.',
            'benefit_type'         => 'disability',
            'benefit_amount'       => 1000000,   // ₱10,000
            'waiting_period_days'  => 180,
            'max_claims_per_year'  => 1,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $calamityProg = DB::table('maf_programs')->insertGetId([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'code'                 => 'CALM-01',
            'name'                 => 'Calamity Assistance',
            'description'          => 'Emergency assistance for members whose household or farm is damaged by typhoon, flood, or fire.',
            'benefit_type'         => 'calamity',
            'benefit_amount'       => 300000,    // ₱3,000
            'waiting_period_days'  => 30,
            'max_claims_per_year'  => 2,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        DB::table('maf_programs')->insert([
            'uuid'                 => (string) Str::uuid(),
            'store_id'             => $store->id,
            'code'                 => 'FUNL-01',
            'name'                 => 'Funeral Assistance',
            'description'          => 'Immediate cash aid to the family to cover funeral expenses upon member\'s death.',
            'benefit_type'         => 'funeral',
            'benefit_amount'       => 500000,    // ₱5,000
            'waiting_period_days'  => 90,
            'max_claims_per_year'  => 1,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // ── Beneficiaries (one per active member minimum) ─────────────────────
        $beneficiaries = [
            // [customer_code, name, relationship, birth_date, contact, is_primary]
            ['MBR-001', 'Maricel B. Macaraeg',    'spouse',  '1982-06-14', '0917-234-5670', true],
            ['MBR-002', 'Ernesto G. Pascual',      'spouse',  '1978-11-02', '0918-345-6780', true],
            ['MBR-003', 'Analyn T. Tolentino',     'spouse',  '1980-03-28', '0919-456-7890', true],
            ['MBR-004', 'Rosario B. Buenaventura', 'child',   '2001-08-15', '0920-567-8901', true],
            ['MBR-005', 'Lilia M. Dizon',          'spouse',  '1975-04-10', '0921-678-9012', true],
            ['MBR-005', 'Romeo A. Dizon Jr.',      'child',   '2003-09-22', '0921-678-9013', false],
            ['MBR-006', 'Ariel P. Valdez',         'spouse',  '1983-07-05', '0922-789-0123', true],
            ['MBR-007', 'Crisanta R. Bautista',    'parent',  '1958-12-30', '0923-890-1234', true],
            ['MBR-008', 'Felipe B. Domingo',       'spouse',  '1985-02-18', '0924-901-2345', true],
            ['MBR-009', 'Luisa C. Ramos',          'spouse',  '1979-05-25', '0925-012-3456', true],
            ['MBR-010', 'Nestor O. Pagtalunan',    'sibling', '1990-10-08', '0926-123-4567', true],
        ];

        $benefIdMap = [];
        foreach ($beneficiaries as [$custCode, $name, $rel, $bday, $contact, $isPrimary]) {
            $customer = Customer::where('code', $custCode)->where('store_id', $store->id)->first();
            $id = DB::table('maf_beneficiaries')->insertGetId([
                'uuid'           => (string) Str::uuid(),
                'store_id'       => $store->id,
                'customer_id'    => $customer->id,
                'name'           => $name,
                'relationship'   => $rel,
                'birth_date'     => $bday,
                'contact_number' => $contact,
                'is_primary'     => $isPrimary,
                'is_active'      => true,
                'notes'          => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            if ($isPrimary) {
                $benefIdMap[$custCode] = $id;
            }
        }

        // ── Contributions: Jan & Feb 2026 for all active members ──────────────
        // Monthly contribution: ₱50 per member (5,000 centavos)
        $activeMbrs = ['MBR-001','MBR-002','MBR-003','MBR-004','MBR-005',
                        'MBR-006','MBR-007','MBR-008','MBR-009'];

        $contribSeq = 1;
        foreach (['2026-01' => 1, '2026-02' => 2] as $yearMonth => $month) {
            foreach ($activeMbrs as $code) {
                $customer = Customer::where('code', $code)->where('store_id', $store->id)->first();
                DB::table('maf_contributions')->insert([
                    'uuid'                => (string) Str::uuid(),
                    'store_id'            => $store->id,
                    'customer_id'         => $customer->id,
                    'user_id'             => $cashier->id,
                    'contribution_number' => 'MAFC-2026-' . str_pad($contribSeq++, 6, '0', STR_PAD_LEFT),
                    'amount'              => 5000,          // ₱50
                    'payment_method'      => 'cash',
                    'reference_number'    => null,
                    'contribution_date'   => $yearMonth . '-15',
                    'period_year'         => 2026,
                    'period_month'        => $month,
                    'notes'               => 'Monthly MAF contribution for ' . $yearMonth . '.',
                    'is_reversed'         => false,
                    'reversed_at'         => null,
                    'reversed_by'         => null,
                    'reversal_reason'      => null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }

        // ── Claim 1: PAID — Rodrigo Tolentino (MBR-003) hospitalization ───────
        $rodrigo    = Customer::where('code', 'MBR-003')->where('store_id', $store->id)->first();
        $rodrigoBenef = $benefIdMap['MBR-003'] ?? null;

        $claim1 = DB::table('maf_claims')->insertGetId([
            'uuid'                  => (string) Str::uuid(),
            'store_id'              => $store->id,
            'customer_id'           => $rodrigo->id,
            'maf_program_id'        => $hospProg,
            'beneficiary_id'        => null,   // member filing for themselves
            'claim_number'          => 'CLAM-2025-000001',
            'benefit_type'          => 'hospitalization',
            'incident_date'         => '2025-10-12',
            'claim_date'            => '2025-10-20',
            'incident_description'  => 'Hospitalized at Nueva Ecija Provincial Hospital for dengue fever from October 12–17, 2025 (5 days confinement).',
            'supporting_documents'  => json_encode(['hospital_bill_2025_10.pdf', 'discharge_summary.pdf']),
            'claimed_amount'        => 500000,   // ₱5,000
            'approved_amount'       => 500000,   // ₱5,000 (full benefit)
            'status'                => 'paid',
            'reviewed_by'           => $manager->id,
            'reviewed_at'           => '2025-10-22 10:00:00',
            'approved_by'           => $gm->id,
            'approved_at'           => '2025-10-25 14:00:00',
            'rejected_by'           => null,
            'rejected_at'           => null,
            'rejection_reason'      => null,
            'paid_by'               => $cashier->id,
            'paid_at'               => '2025-10-28 09:00:00',
            'notes'                 => 'Claim verified with hospital records. Full benefit approved by BOD.',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // Claim payment
        DB::table('maf_claim_payments')->insert([
            'uuid'             => (string) Str::uuid(),
            'store_id'         => $store->id,
            'claim_id'         => $claim1,
            'customer_id'      => $rodrigo->id,
            'user_id'          => $cashier->id,
            'payment_number'   => 'MAFP-2025-000001',
            'amount'           => 500000,
            'payment_method'   => 'cash',
            'reference_number' => null,
            'payment_date'     => '2025-10-28',
            'notes'            => 'Cash disbursed to member at main office window.',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // ── Claim 2: UNDER REVIEW — Bernardo Macaraeg (MBR-001) calamity ─────
        $bernardo = Customer::where('code', 'MBR-001')->where('store_id', $store->id)->first();

        DB::table('maf_claims')->insert([
            'uuid'                  => (string) Str::uuid(),
            'store_id'              => $store->id,
            'customer_id'           => $bernardo->id,
            'maf_program_id'        => $calamityProg,
            'beneficiary_id'        => null,
            'claim_number'          => 'CLAM-2026-000001',
            'benefit_type'          => 'calamity',
            'incident_date'         => '2026-01-28',
            'claim_date'            => '2026-02-03',
            'incident_description'  => 'Rice farm flooded due to burst irrigation canal on January 28, 2026. Estimated crop loss of ₱25,000 on 1.5 ha. Farm tools and irrigation equipment also damaged.',
            'supporting_documents'  => json_encode(['barangay_calamity_cert.pdf', 'farm_damage_photos.zip']),
            'claimed_amount'        => 300000,   // ₱3,000 (max benefit)
            'approved_amount'       => null,
            'status'                => 'under_review',
            'reviewed_by'           => $manager->id,
            'reviewed_at'           => '2026-02-05 11:00:00',
            'approved_by'           => null,
            'approved_at'           => null,
            'rejected_by'           => null,
            'rejected_at'           => null,
            'rejection_reason'      => null,
            'paid_by'               => null,
            'paid_at'               => null,
            'notes'                 => 'Awaiting site inspection by Audit Committee. BOD to act on this at next meeting March 5, 2026.',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }
}
