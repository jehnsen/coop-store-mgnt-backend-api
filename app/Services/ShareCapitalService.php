<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\MemberShareAccount;
use App\Models\ShareCapitalPayment;
use App\Models\ShareCertificate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShareCapitalService
{
    // =========================================================================
    // Share Account Management
    // =========================================================================

    /**
     * Open a new share capital account for a member.
     *
     * @throws RuntimeException if the customer is not a cooperative member.
     */
    public function openShareAccount(array $data): MemberShareAccount
    {
        $customer = Customer::where('uuid', $data['customer_uuid'])->firstOrFail();

        if (! $customer->is_member) {
            throw new RuntimeException('Only cooperative members can open a share capital account.');
        }

        return DB::transaction(function () use ($data, $customer) {
            $parValueCentavos        = (int) round($data['par_value_per_share'] * 100);
            $subscribedShares        = (int) $data['subscribed_shares'];
            $totalSubscribedCentavos = $parValueCentavos * $subscribedShares;

            $account = MemberShareAccount::create([
                'customer_id'             => $customer->id,
                'share_type'              => $data['share_type'] ?? 'regular',
                'subscribed_shares'       => $subscribedShares,
                'par_value_per_share'     => $parValueCentavos,
                'total_subscribed_amount' => $totalSubscribedCentavos,
                'total_paid_up_amount'    => 0,
                'status'                  => 'active',
                'opened_date'             => $data['opened_date'] ?? now()->toDateString(),
                'notes'                   => $data['notes'] ?? null,
            ]);

            activity()
                ->performedOn($account)
                ->causedBy(auth()->user())
                ->withProperties(['customer_id' => $customer->id, 'subscribed_shares' => $subscribedShares])
                ->log('share_account_opened');

            return $account;
        });
    }

    /**
     * Record a share capital payment installment.
     *
     * @throws RuntimeException if payment would exceed the total subscribed amount.
     */
    public function recordPayment(MemberShareAccount $account, array $data, User $operator): ShareCapitalPayment
    {
        if ($account->status !== 'active') {
            throw new RuntimeException('Cannot record a payment on a non-active share account.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            // Work in raw centavos to avoid accessor precision issues
            $currentPaidCentavos     = $account->getRawOriginal('total_paid_up_amount');
            $totalSubscribedCentavos = $account->getRawOriginal('total_subscribed_amount');
            $amountCentavos          = (int) round($data['amount'] * 100);

            if ($currentPaidCentavos + $amountCentavos > $totalSubscribedCentavos) {
                $remainingCentavos = $totalSubscribedCentavos - $currentPaidCentavos;
                throw new RuntimeException(
                    sprintf(
                        'Payment of ₱%s exceeds remaining subscription of ₱%s.',
                        number_format($amountCentavos / 100, 2),
                        number_format($remainingCentavos / 100, 2),
                    )
                );
            }

            $balanceBefore = $currentPaidCentavos;
            $balanceAfter  = $currentPaidCentavos + $amountCentavos;

            $payment = ShareCapitalPayment::create([
                'customer_id'      => $account->customer_id,
                'share_account_id' => $account->id,
                'user_id'          => $operator->id,
                'amount'           => $amountCentavos,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'payment_date'     => $data['payment_date'] ?? now()->toDateString(),
                'notes'            => $data['notes'] ?? null,
            ]);

            // Update running total on the account (raw centavos, bypass accessor)
            $account->timestamps = false;
            DB::table('member_share_accounts')
                ->where('id', $account->id)
                ->update(['total_paid_up_amount' => $balanceAfter]);
            $account->timestamps = true;
            $account->refresh();

            activity()
                ->performedOn($payment)
                ->causedBy($operator)
                ->withProperties(['amount_centavos' => $amountCentavos, 'balance_after' => $balanceAfter])
                ->log('share_payment_recorded');

            return $payment;
        });
    }

    /**
     * Reverse a share capital payment (e.g. bounced cheque).
     *
     * @throws RuntimeException if the payment is already reversed.
     */
    public function reversePayment(ShareCapitalPayment $payment, User $operator): ShareCapitalPayment
    {
        if ($payment->is_reversed) {
            throw new RuntimeException('This payment has already been reversed.');
        }

        $account = $payment->shareAccount;

        if ($account->status === 'withdrawn') {
            throw new RuntimeException('Cannot reverse a payment on a withdrawn share account.');
        }

        return DB::transaction(function () use ($payment, $account, $operator) {
            $amountCentavos        = $payment->getRawOriginal('amount');
            $currentPaidCentavos   = $account->getRawOriginal('total_paid_up_amount');
            $newPaidCentavos       = max(0, $currentPaidCentavos - $amountCentavos);

            $payment->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $operator->id,
            ]);

            DB::table('member_share_accounts')
                ->where('id', $account->id)
                ->update(['total_paid_up_amount' => $newPaidCentavos]);

            activity()
                ->performedOn($payment)
                ->causedBy($operator)
                ->withProperties(['reversed_amount_centavos' => $amountCentavos])
                ->log('share_payment_reversed');

            return $payment->fresh();
        });
    }

    // =========================================================================
    // Certificate Management
    // =========================================================================

    /**
     * Issue a share certificate for paid-up shares.
     *
     * @throws RuntimeException if shares_covered exceeds paid-up equivalent.
     */
    public function issueCertificate(MemberShareAccount $account, array $data, User $operator): ShareCertificate
    {
        if ($account->status !== 'active') {
            throw new RuntimeException('Cannot issue a certificate on a non-active share account.');
        }

        $sharesCovered           = (int) $data['shares_covered'];
        $parValueCentavos        = $account->getRawOriginal('par_value_per_share');
        $totalPaidCentavos       = $account->getRawOriginal('total_paid_up_amount');
        $paidUpShares            = $parValueCentavos > 0 ? (int) floor($totalPaidCentavos / $parValueCentavos) : 0;

        if ($sharesCovered > $paidUpShares) {
            throw new RuntimeException(
                "Cannot issue certificate for {$sharesCovered} shares. Only {$paidUpShares} shares are fully paid up."
            );
        }

        return DB::transaction(function () use ($account, $data, $sharesCovered, $parValueCentavos, $operator) {
            $faceValueCentavos = $sharesCovered * $parValueCentavos;

            $certificate = ShareCertificate::create([
                'customer_id'      => $account->customer_id,
                'share_account_id' => $account->id,
                'shares_covered'   => $sharesCovered,
                'face_value'       => $faceValueCentavos,
                'issue_date'       => $data['issue_date'] ?? now()->toDateString(),
                'issued_by'        => $operator->id,
                'status'           => 'active',
            ]);

            activity()
                ->performedOn($certificate)
                ->causedBy($operator)
                ->withProperties(['shares_covered' => $sharesCovered, 'face_value_centavos' => $faceValueCentavos])
                ->log('share_certificate_issued');

            return $certificate;
        });
    }

    /**
     * Cancel a share certificate.
     */
    public function cancelCertificate(ShareCertificate $certificate, string $reason, User $operator): ShareCertificate
    {
        if ($certificate->status === 'cancelled') {
            throw new RuntimeException('This certificate is already cancelled.');
        }

        $certificate->update([
            'status'               => 'cancelled',
            'cancelled_at'         => now(),
            'cancelled_by'         => $operator->id,
            'cancellation_reason'  => $reason,
        ]);

        activity()
            ->performedOn($certificate)
            ->causedBy($operator)
            ->withProperties(['reason' => $reason])
            ->log('share_certificate_cancelled');

        return $certificate->fresh();
    }

    // =========================================================================
    // Account Lifecycle
    // =========================================================================

    /**
     * Process a member's share withdrawal (closes the account).
     */
    public function withdrawShares(MemberShareAccount $account, array $data, User $operator): MemberShareAccount
    {
        if ($account->status === 'withdrawn') {
            throw new RuntimeException('This share account has already been withdrawn.');
        }

        return DB::transaction(function () use ($account, $data, $operator) {
            // Cancel all active certificates
            ShareCertificate::where('share_account_id', $account->id)
                ->where('status', 'active')
                ->get()
                ->each(fn ($cert) => $this->cancelCertificate($cert, 'Account withdrawn by member.', $operator));

            $account->update([
                'status'         => 'withdrawn',
                'withdrawn_date' => $data['withdrawn_date'] ?? now()->toDateString(),
                'withdrawn_by'   => $operator->id,
                'notes'          => $data['notes'] ?? $account->notes,
            ]);

            activity()
                ->performedOn($account)
                ->causedBy($operator)
                ->log('share_account_withdrawn');

            return $account->fresh();
        });
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Generate an account statement for a date range.
     */
    public function getStatement(MemberShareAccount $account, Carbon $from, Carbon $to): array
    {
        $payments = ShareCapitalPayment::where('share_account_id', $account->id)
            ->where('is_reversed', false)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // Opening balance: sum of non-reversed payments BEFORE $from
        $openingCentavos = ShareCapitalPayment::where('share_account_id', $account->id)
            ->where('is_reversed', false)
            ->where('payment_date', '<', $from->toDateString())
            ->sum('amount');

        return [
            'account'          => $account->load('customer:id,uuid,name,member_id'),
            'period_from'      => $from->toDateString(),
            'period_to'        => $to->toDateString(),
            'opening_balance'  => $openingCentavos / 100,
            'payments'         => $payments,
            'closing_balance'  => $account->getRawOriginal('total_paid_up_amount') / 100,
            'total_subscribed' => $account->getRawOriginal('total_subscribed_amount') / 100,
        ];
    }

    /**
     * Compute Interest on Share Capital (ISC) for a fiscal year.
     *
     * Uses a weighted daily-average method:
     *   - For each member account, reconstruct the paid-up balance on each day
     *     of the year from the payment history.
     *   - Average daily balance × annual dividend_rate = ISC for the member.
     *
     * @param  int    $storeId       The cooperative's store ID.
     * @param  int    $year          Fiscal year (e.g. 2026).
     * @param  float  $dividendRate  Annual ISC rate (e.g. 0.12 = 12 %).
     */
    public function computeISC(int $storeId, int $year, float $dividendRate): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();
        $totalDays = $yearStart->diffInDays($yearEnd) + 1;

        $accounts = MemberShareAccount::where('store_id', $storeId)
            ->where('opened_date', '<=', $yearEnd->toDateString())
            ->whereIn('status', ['active', 'withdrawn'])
            ->with('customer:id,uuid,name,member_id')
            ->get();

        $rows          = [];
        $grandTotalISC = 0;

        foreach ($accounts as $account) {
            // Get all non-reversed payments up to year-end, ordered chronologically
            $payments = ShareCapitalPayment::where('share_account_id', $account->id)
                ->where('is_reversed', false)
                ->where('payment_date', '<=', $yearEnd->toDateString())
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get(['payment_date', 'amount']);

            // Build daily balance timeline within the year
            // Start with the balance on Jan 1 (from payments before the year)
            $balanceCentavos = ShareCapitalPayment::where('share_account_id', $account->id)
                ->where('is_reversed', false)
                ->where('payment_date', '<', $yearStart->toDateString())
                ->sum('amount');

            // Payments within the year, indexed by date string
            $paymentsInYear = $payments
                ->where('payment_date', '>=', $yearStart->toDateString())
                ->groupBy(fn ($p) => Carbon::parse($p->payment_date)->toDateString());

            // Weighted sum: balance × days held at that balance
            $weightedSum      = 0;
            $currentBalance   = $balanceCentavos;
            $currentDate      = $yearStart->copy();

            // Collect change dates within the year
            $changeDates = $paymentsInYear->keys()->sort()->values()->all();
            $changeDates[] = $yearEnd->addDay()->toDateString(); // sentinel

            foreach ($changeDates as $changeDate) {
                $changeCarbon = Carbon::parse($changeDate);
                $daysHeld     = (int) $currentDate->diffInDays(min($changeCarbon, $yearEnd->copy()->addDay()));

                $weightedSum    += $currentBalance * $daysHeld;
                $currentDate     = $changeCarbon->copy();

                // Apply payments on this date
                if (isset($paymentsInYear[$changeDate])) {
                    foreach ($paymentsInYear[$changeDate] as $pmt) {
                        $currentBalance += $pmt->getRawOriginal('amount');
                    }
                }
            }

            $averageDailyBalance = $totalDays > 0 ? $weightedSum / $totalDays : 0;
            $iscCentavos         = (int) round($averageDailyBalance * $dividendRate);
            $grandTotalISC      += $iscCentavos;

            $rows[] = [
                'customer'              => [
                    'uuid'      => $account->customer->uuid ?? null,
                    'name'      => $account->customer->name ?? null,
                    'member_id' => $account->customer->member_id ?? null,
                ],
                'account_number'        => $account->account_number,
                'share_type'            => $account->share_type,
                'average_paid_up'       => round($averageDailyBalance / 100, 2),
                'isc_amount'            => round($iscCentavos / 100, 2),
                'total_paid_up_current' => $account->getRawOriginal('total_paid_up_amount') / 100,
            ];
        }

        return [
            'year'                 => $year,
            'dividend_rate'        => $dividendRate,
            'total_members'        => count($rows),
            'total_isc_declared'   => round($grandTotalISC / 100, 2),
            'members'              => $rows,
        ];
    }

    /**
     * Portfolio overview statistics.
     */
    public function getPortfolioOverview(int $storeId): array
    {
        $accounts = MemberShareAccount::where('store_id', $storeId)->get();

        $activeAccounts = $accounts->where('status', 'active');

        $totalSubscribed  = $accounts->sum(fn ($a) => $a->getRawOriginal('total_subscribed_amount'));
        $totalPaidUp      = $accounts->sum(fn ($a) => $a->getRawOriginal('total_paid_up_amount'));
        $totalUnpaid      = $totalSubscribed - $totalPaidUp;
        $fullyPaidCount   = $activeAccounts->filter(
            fn ($a) => $a->getRawOriginal('total_paid_up_amount') >= $a->getRawOriginal('total_subscribed_amount')
        )->count();

        return [
            'total_accounts'          => $accounts->count(),
            'active_accounts'         => $activeAccounts->count(),
            'withdrawn_accounts'      => $accounts->where('status', 'withdrawn')->count(),
            'total_subscribed'        => round($totalSubscribed / 100, 2),
            'total_paid_up'           => round($totalPaidUp / 100, 2),
            'total_unpaid_subscription' => round($totalUnpaid / 100, 2),
            'fully_paid_members'      => $fullyPaidCount,
            'collection_rate_pct'     => $totalSubscribed > 0 ? round(($totalPaidUp / $totalSubscribed) * 100, 2) : 0,
        ];
    }
}
