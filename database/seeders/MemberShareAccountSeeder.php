<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Seeds member_share_accounts, share_capital_payments, share_certificates.
 *
 * Par value: ₱100/share (10,000 centavos).
 * All active members subscribe 20–60 shares; payments spread over their tenure.
 * Older members are fully paid-up; newer ones are partially paid.
 */
class MemberShareAccountSeeder extends Seeder
{
    public function run(): void
    {
        $store     = Store::first();
        $cashier   = User::where('email', 'rowena.castillo@snlsimpc.coop')->first();
        $manager   = User::where('email', 'evelyn.buenaventura@snlsimpc.coop')->first();
        $parValue  = 10000; // ₱100 per share in centavos

        // code, subscribed_shares, joined_year, paidUpShares (how many fully paid so far)
        $members = [
            ['MBR-001', 30, 2018, 30],   // 30 shares, fully paid-up — ₱3,000 total
            ['MBR-002', 20, 2019, 20],   // 20 shares, fully paid-up — ₱2,000 total
            ['MBR-003', 50, 2017, 50],   // 50 shares, fully paid-up — ₱5,000 total
            ['MBR-004', 20, 2020, 14],   // 20 subscribed, 14 paid — ₱1,400 paid of ₱2,000
            ['MBR-005', 60, 2016, 60],   // 60 shares, fully paid-up — ₱6,000 total
            ['MBR-006', 20, 2021, 12],   // 20 subscribed, 12 paid — ₱1,200 of ₱2,000
            ['MBR-007', 20, 2019, 20],   // 20 shares, fully paid-up — ₱2,000 total
            ['MBR-008', 20, 2022, 8],    // 20 subscribed, 8 paid — ₱800 of ₱2,000
            ['MBR-009', 40, 2018, 40],   // 40 shares, fully paid-up — ₱4,000 total
            ['MBR-010', 20, 2020, 10],   // 20 subscribed, 10 paid (inactive) — ₱1,000 of ₱2,000
        ];

        $acctSeq = 1;
        $paySeq  = 1;
        $certSeq = 1;

        foreach ($members as [$code, $subShares, $joinedYear, $paidShares]) {
            $customer   = Customer::where('code', $code)->where('store_id', $store->id)->first();
            $totalSub   = $subShares * $parValue;
            $totalPaid  = $paidShares * $parValue;
            $status     = $code === 'MBR-010' ? 'suspended' : 'active';
            $openedDate = $joinedYear . '-04-01';
            $acctNum    = 'SHA-' . $joinedYear . '-' . str_pad($acctSeq++, 6, '0', STR_PAD_LEFT);

            $shareAccount = DB::table('member_share_accounts')->insertGetId([
                'uuid'                  => (string) Str::uuid(),
                'store_id'              => $store->id,
                'customer_id'           => $customer->id,
                'account_number'        => $acctNum,
                'share_type'            => 'regular',
                'subscribed_shares'     => $subShares,
                'par_value_per_share'   => $parValue,
                'total_subscribed_amount' => $totalSub,
                'total_paid_up_amount'  => $totalPaid,
                'status'                => $status,
                'opened_date'           => $openedDate,
                'withdrawn_date'        => null,
                'withdrawn_by'          => null,
                'notes'                 => null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            // ── Payments: spread across years ───────────────────────────────
            // Typically members pay ₱500–₱1,000/year (50–100 centavos/share batches)
            $remainingPaid = $totalPaid;
            $balanceBefore = 0;
            $payYear       = $joinedYear;

            while ($remainingPaid > 0 && $payYear <= 2025) {
                // Pay ₱500 (50,000 centavos) per year, or whatever remains
                $yearPayment = min(50000, $remainingPaid);
                $balanceAfter = $balanceBefore + $yearPayment;

                DB::table('share_capital_payments')->insert([
                    'uuid'             => (string) Str::uuid(),
                    'store_id'         => $store->id,
                    'customer_id'      => $customer->id,
                    'share_account_id' => $shareAccount,
                    'user_id'          => $cashier->id,
                    'payment_number'   => 'SCP-' . $payYear . '-' . str_pad($paySeq++, 6, '0', STR_PAD_LEFT),
                    'amount'           => $yearPayment,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'payment_method'   => 'cash',
                    'reference_number' => null,
                    'payment_date'     => $payYear . '-01-20',
                    'notes'            => 'Annual share capital payment for ' . $payYear . '.',
                    'is_reversed'      => false,
                    'reversed_at'      => null,
                    'reversed_by'      => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $balanceBefore  = $balanceAfter;
                $remainingPaid -= $yearPayment;
                $payYear++;
            }

            // ── Share Certificate: issue one for fully-paid-up accounts ─────
            if ($paidShares >= $subShares) {
                DB::table('share_certificates')->insert([
                    'uuid'               => (string) Str::uuid(),
                    'store_id'           => $store->id,
                    'customer_id'        => $customer->id,
                    'share_account_id'   => $shareAccount,
                    'certificate_number' => 'SC-' . $joinedYear . '-' . str_pad($certSeq++, 6, '0', STR_PAD_LEFT),
                    'shares_covered'     => $paidShares,
                    'face_value'         => $paidShares * $parValue,
                    'issue_date'         => ($payYear - 1) . '-02-01',
                    'issued_by'          => $manager->id,
                    'status'             => 'active',
                    'cancelled_at'       => null,
                    'cancelled_by'       => null,
                    'cancellation_reason' => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }
}
