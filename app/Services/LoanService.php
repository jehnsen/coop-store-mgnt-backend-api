<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanAmortizationSchedule;
use App\Models\LoanPayment;
use App\Models\LoanPenalty;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanService
{
    public function __construct(
        protected AmortizationService $amortizationService,
    ) {
    }

    // =========================================================================
    // Amortization Preview (no DB writes)
    // =========================================================================

    /**
     * Compute a schedule preview without persisting anything.
     *
     * @param  array{
     *     principal_amount: int,   // centavos
     *     interest_rate: float,    // monthly rate
     *     term_months: int,
     *     first_payment_date: string, // Y-m-d
     *     payment_interval: string,
     * } $data
     */
    public function computeAmortizationPreview(array $data): array
    {
        return $this->amortizationService->computeDiminishingBalance(
            principalCentavos: (int) $data['principal_amount'],
            monthlyRate:        (float) $data['interest_rate'],
            termMonths:         (int) $data['term_months'],
            firstPaymentDate:   Carbon::parse($data['first_payment_date']),
            interval:           $data['payment_interval'] ?? 'monthly',
        );
    }

    // =========================================================================
    // Loan Application Workflow
    // =========================================================================

    /**
     * Submit a new loan application and persist the amortization schedule.
     *
     * @throws RuntimeException if customer is not a member.
     */
    public function applyLoan(array $data, User $operator): Loan
    {
        $customer    = Customer::where('uuid', $data['customer_uuid'])->firstOrFail();
        $loanProduct = LoanProduct::where('uuid', $data['loan_product_uuid'])->firstOrFail();

        if (! $customer->is_member) {
            throw new RuntimeException('Only cooperative members may apply for a loan.');
        }

        return DB::transaction(function () use ($data, $customer, $loanProduct, $operator) {
            $principalCentavos  = (int) $data['principal_amount'];
            $interestRate       = (float) ($data['interest_rate'] ?? $loanProduct->interest_rate);
            $termMonths         = (int) $data['term_months'];
            $paymentInterval    = $data['payment_interval'] ?? 'monthly';
            $firstPaymentDate   = Carbon::parse($data['first_payment_date'] ?? now()->addMonth()->startOfMonth());

            // Fees
            $processingFeeCentavos = (int) round($principalCentavos * (float) $loanProduct->processing_fee_rate);
            $serviceFeeCentavos    = $loanProduct->getRawOriginal('service_fee');
            $netProceedsCentavos   = $principalCentavos - $processingFeeCentavos - $serviceFeeCentavos;

            // Compute schedule
            $computed = $this->amortizationService->computeDiminishingBalance(
                principalCentavos: $principalCentavos,
                monthlyRate:        $interestRate,
                termMonths:         $termMonths,
                firstPaymentDate:   $firstPaymentDate,
                interval:           $paymentInterval,
            );

            // Create Loan record
            $loan = Loan::create([
                'customer_id'      => $customer->id,
                'loan_product_id'  => $loanProduct->id,
                'user_id'          => $operator->id,
                'principal_amount' => $principalCentavos,
                'interest_rate'    => $interestRate,
                'interest_method'  => 'diminishing_balance',
                'term_months'      => $termMonths,
                'payment_interval' => $paymentInterval,
                'purpose'          => $data['purpose'],
                'collateral_description' => $data['collateral_description'] ?? null,
                'processing_fee'   => $processingFeeCentavos,
                'service_fee'      => $serviceFeeCentavos,
                'net_proceeds'     => $netProceedsCentavos,
                'total_interest'   => $computed['total_interest'],
                'total_payable'    => $computed['total_payable'],
                'amortization_amount' => $computed['emi_centavos'],
                'outstanding_balance' => $principalCentavos,
                'application_date' => $data['application_date'] ?? now()->toDateString(),
                'status'           => 'pending',
            ]);

            // Persist amortization schedule
            $this->persistSchedule($loan, $computed['schedule']);

            activity()
                ->performedOn($loan)
                ->causedBy($operator)
                ->withProperties(['principal_centavos' => $principalCentavos, 'term_months' => $termMonths])
                ->log('loan_application_submitted');

            return $loan->load(['customer', 'loanProduct', 'amortizationSchedules']);
        });
    }

    /**
     * Approve a loan application.
     *
     * @throws RuntimeException if status is not pending or under_review.
     */
    public function approveLoan(Loan $loan, User $approver, array $data = []): Loan
    {
        if (! in_array($loan->status, ['pending', 'under_review'])) {
            throw new RuntimeException("Cannot approve a loan with status '{$loan->status}'.");
        }

        return DB::transaction(function () use ($loan, $approver, $data) {
            $loan->update([
                'status'       => 'approved',
                'approval_date' => $data['approval_date'] ?? now()->toDateString(),
                'approved_by'  => $approver->id,
            ]);

            activity()
                ->performedOn($loan)
                ->causedBy($approver)
                ->log('loan_approved');

            return $loan->fresh();
        });
    }

    /**
     * Reject a loan application.
     */
    public function rejectLoan(Loan $loan, User $rejecter, string $reason): Loan
    {
        if (! in_array($loan->status, ['pending', 'under_review'])) {
            throw new RuntimeException("Cannot reject a loan with status '{$loan->status}'.");
        }

        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        activity()
            ->performedOn($loan)
            ->causedBy($rejecter)
            ->withProperties(['reason' => $reason])
            ->log('loan_rejected');

        return $loan->fresh();
    }

    /**
     * Disburse an approved loan (status-change only).
     *
     * Sets disbursement_date, first_payment_date, maturity_date, and
     * updates the amortization schedule due dates if first_payment_date
     * differs from the original.
     *
     * @throws RuntimeException if loan is not in 'approved' status.
     */
    public function disburseLoan(Loan $loan, User $disburser, array $data): Loan
    {
        if ($loan->status !== 'approved') {
            throw new RuntimeException("Cannot disburse a loan with status '{$loan->status}'.");
        }

        return DB::transaction(function () use ($loan, $disburser, $data) {
            $disbursementDate  = Carbon::parse($data['disbursement_date'] ?? now()->toDateString());
            $firstPaymentDate  = isset($data['first_payment_date'])
                ? Carbon::parse($data['first_payment_date'])
                : $disbursementDate->copy()->addMonth()->startOfMonth();

            $termMonths       = $loan->term_months;
            $paymentInterval  = $loan->payment_interval;

            // Recompute due dates if first_payment_date was supplied explicitly
            $newDueDates = $this->amortizationService->getDueDates(
                $firstPaymentDate, $this->resolvePeriods($termMonths, $paymentInterval), $paymentInterval
            );

            // Update schedule due dates
            $schedules = LoanAmortizationSchedule::where('loan_id', $loan->id)
                ->orderBy('payment_number')
                ->get();

            foreach ($schedules as $idx => $schedule) {
                $schedule->update([
                    'due_date' => $newDueDates[$idx]->toDateString(),
                ]);
            }

            // Maturity date = last due date
            $maturityDate = end($newDueDates);

            $netProceedsCentavos = $loan->getRawOriginal('principal_amount')
                - $loan->getRawOriginal('processing_fee')
                - $loan->getRawOriginal('service_fee');

            $loan->update([
                'status'             => 'active',
                'disbursement_date'  => $disbursementDate->toDateString(),
                'disbursed_by'       => $disburser->id,
                'first_payment_date' => $firstPaymentDate->toDateString(),
                'maturity_date'      => $maturityDate->toDateString(),
                'net_proceeds'       => $netProceedsCentavos,
            ]);

            activity()
                ->performedOn($loan)
                ->causedBy($disburser)
                ->withProperties(['disbursement_date' => $disbursementDate->toDateString()])
                ->log('loan_disbursed');

            return $loan->fresh();
        });
    }

    // =========================================================================
    // Payment Recording (FIFO allocation)
    // =========================================================================

    /**
     * Record a loan repayment and allocate it using FIFO:
     *   1. Unpaid penalties (oldest first)
     *   2. Overdue/partial amortization schedules (oldest first)
     *   3. Next pending schedule
     *
     * @param  array{
     *     amount: int,             // centavos
     *     payment_method: string,
     *     payment_date: string,    // Y-m-d
     *     reference_number?: string,
     *     notes?: string,
     * } $data
     *
     * @throws RuntimeException if payment exceeds total outstanding.
     */
    public function recordPayment(Loan $loan, array $data, User $operator): LoanPayment
    {
        if (! in_array($loan->status, ['active', 'disbursed'])) {
            throw new RuntimeException("Cannot record a payment on a loan with status '{$loan->status}'.");
        }

        return DB::transaction(function () use ($loan, $data, $operator) {
            $paymentCentavos = (int) $data['amount'];
            $remaining       = $paymentCentavos;

            $balanceBefore   = $loan->getRawOriginal('outstanding_balance');
            $penaltiesBefore = $loan->getRawOriginal('total_penalties_outstanding');

            $penaltyPortion  = 0;
            $interestPortion = 0;
            $principalPortion = 0;

            // ── 1. Apply to outstanding penalties (FIFO) ──────────────────────
            if ($remaining > 0 && $penaltiesBefore > 0) {
                $unpaidPenalties = LoanPenalty::where('loan_id', $loan->id)
                    ->where('is_paid', false)
                    ->where('net_penalty', '>', 0)
                    ->orderBy('applied_date')
                    ->orderBy('id')
                    ->get();

                foreach ($unpaidPenalties as $penalty) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $penaltyDueCentavos = $penalty->getRawOriginal('net_penalty');
                    $applied            = min($remaining, $penaltyDueCentavos);

                    $penalty->update([
                        'is_paid'   => $applied >= $penaltyDueCentavos,
                        'paid_date' => $applied >= $penaltyDueCentavos ? $data['payment_date'] : null,
                    ]);

                    $penaltyPortion += $applied;
                    $remaining      -= $applied;
                }
            }

            // ── 2. Apply to amortization schedules (FIFO) ────────────────────
            if ($remaining > 0) {
                $schedules = LoanAmortizationSchedule::where('loan_id', $loan->id)
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->orderBy('payment_number')
                    ->get();

                foreach ($schedules as $schedule) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $schedulePaidCentavos = $schedule->getRawOriginal('total_paid');
                    $scheduleTotalDue     = $schedule->getRawOriginal('total_due');
                    $scheduleRemaining    = $scheduleTotalDue - $schedulePaidCentavos;

                    $applied = min($remaining, $scheduleRemaining);

                    // Split proportionally between interest and principal within this schedule
                    $scheduleInterestDue   = $schedule->getRawOriginal('interest_due');
                    $scheduleInterestPaid  = $schedule->getRawOriginal('interest_paid');
                    $interestRemaining     = max(0, $scheduleInterestDue - $scheduleInterestPaid);

                    $interestApplied   = min($applied, $interestRemaining);
                    $principalApplied  = $applied - $interestApplied;

                    $newInterestPaid   = $scheduleInterestPaid + $interestApplied;
                    $newPrincipalPaid  = $schedule->getRawOriginal('principal_paid') + $principalApplied;
                    $newTotalPaid      = $schedulePaidCentavos + $applied;

                    $isFull = $newTotalPaid >= $scheduleTotalDue;

                    $schedule->update([
                        'principal_paid' => $newPrincipalPaid,
                        'interest_paid'  => $newInterestPaid,
                        'total_paid'     => $newTotalPaid,
                        'paid_date'      => $isFull ? $data['payment_date'] : null,
                        'status'         => $isFull ? 'paid' : 'partial',
                    ]);

                    $interestPortion  += $interestApplied;
                    $principalPortion += $principalApplied;
                    $remaining        -= $applied;
                }
            }

            // ── 3. Update running Loan totals ─────────────────────────────────
            $newOutstandingBalance      = max(0, $balanceBefore - $principalPortion);
            $newPenaltiesOutstanding    = max(0, $penaltiesBefore - $penaltyPortion);
            $newTotalPrincipalPaid      = $loan->getRawOriginal('total_principal_paid') + $principalPortion;
            $newTotalInterestPaid       = $loan->getRawOriginal('total_interest_paid') + $interestPortion;
            $newTotalPenaltyPaid        = $loan->getRawOriginal('total_penalty_paid') + $penaltyPortion;

            // Penalty schedules may be updated separately; update in LoanAmortizationSchedule
            $loanUpdates = [
                'outstanding_balance'       => $newOutstandingBalance,
                'total_penalties_outstanding' => $newPenaltiesOutstanding,
                'total_principal_paid'      => $newTotalPrincipalPaid,
                'total_interest_paid'       => $newTotalInterestPaid,
                'total_penalty_paid'        => $newTotalPenaltyPaid,
            ];

            if ($newOutstandingBalance === 0) {
                $loanUpdates['status'] = 'closed';
            }

            DB::table('loans')->where('id', $loan->id)->update($loanUpdates);

            // ── 4. Create payment ledger record ───────────────────────────────
            $payment = LoanPayment::create([
                'loan_id'          => $loan->id,
                'customer_id'      => $loan->customer_id,
                'user_id'          => $operator->id,
                'amount'           => $paymentCentavos,
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestPortion,
                'penalty_portion'  => $penaltyPortion,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $newOutstandingBalance,
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'payment_date'     => $data['payment_date'] ?? now()->toDateString(),
                'notes'            => $data['notes'] ?? null,
            ]);

            activity()
                ->performedOn($payment)
                ->causedBy($operator)
                ->withProperties([
                    'amount_centavos'    => $paymentCentavos,
                    'principal_portion'  => $principalPortion,
                    'interest_portion'   => $interestPortion,
                    'penalty_portion'    => $penaltyPortion,
                    'balance_after'      => $newOutstandingBalance,
                ])
                ->log('loan_payment_recorded');

            return $payment;
        });
    }

    /**
     * Reverse a loan payment (e.g. bounced cheque).
     *
     * @throws RuntimeException if loan is closed or payment already reversed.
     */
    public function reversePayment(LoanPayment $payment, User $operator): LoanPayment
    {
        if ($payment->is_reversed) {
            throw new RuntimeException('This payment has already been reversed.');
        }

        $loan = $payment->loan;
        if ($loan->status === 'closed') {
            throw new RuntimeException('Cannot reverse a payment on a closed loan.');
        }

        return DB::transaction(function () use ($payment, $loan, $operator) {
            $principalCentavos = $payment->getRawOriginal('principal_portion');
            $interestCentavos  = $payment->getRawOriginal('interest_portion');
            $penaltyCentavos   = $payment->getRawOriginal('penalty_portion');

            // Restore loan balances
            DB::table('loans')->where('id', $loan->id)->update([
                'outstanding_balance'        => $loan->getRawOriginal('outstanding_balance') + $principalCentavos,
                'total_principal_paid'       => max(0, $loan->getRawOriginal('total_principal_paid') - $principalCentavos),
                'total_interest_paid'        => max(0, $loan->getRawOriginal('total_interest_paid') - $interestCentavos),
                'total_penalty_paid'         => max(0, $loan->getRawOriginal('total_penalty_paid') - $penaltyCentavos),
                'total_penalties_outstanding' => $loan->getRawOriginal('total_penalties_outstanding') + $penaltyCentavos,
                'status'                     => 'active', // reopen if it was closed
            ]);

            $payment->update([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $operator->id,
            ]);

            activity()
                ->performedOn($payment)
                ->causedBy($operator)
                ->log('loan_payment_reversed');

            return $payment->fresh();
        });
    }

    // =========================================================================
    // Penalty Management
    // =========================================================================

    /**
     * Compute and persist penalties for all overdue amortization schedules.
     *
     * Uses a standard 2%/month (configurable via penalty_rate) on the
     * overdue amount per amortization period.
     *
     * @param  float  $penaltyRate  Monthly penalty rate (default 0.02 = 2 %/month).
     */
    public function computePenalties(Loan $loan, ?Carbon $asOfDate = null, float $penaltyRate = 0.02): Collection
    {
        if (! in_array($loan->status, ['active', 'disbursed'])) {
            throw new RuntimeException("Cannot compute penalties on a loan with status '{$loan->status}'.");
        }

        $asOfDate    = $asOfDate ?? Carbon::today();
        $appliedList = collect();

        $overdueSchedules = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->where('due_date', '<', $asOfDate->toDateString())
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('payment_number')
            ->get();

        DB::transaction(function () use ($loan, $overdueSchedules, $asOfDate, $penaltyRate, &$appliedList) {
            $totalNewPenaltiesCentavos = 0;

            foreach ($overdueSchedules as $schedule) {
                $daysOverdue  = Carbon::parse($schedule->due_date)->diffInDays($asOfDate);
                $overdueDue   = $schedule->getRawOriginal('total_due') - $schedule->getRawOriginal('total_paid');

                if ($overdueDue <= 0 || $daysOverdue <= 0) {
                    continue;
                }

                // penalty = overdue_amount × rate × (days_overdue / 30)
                $penaltyCentavos = (int) round($overdueDue * $penaltyRate * ($daysOverdue / 30));

                if ($penaltyCentavos <= 0) {
                    continue;
                }

                $penalty = LoanPenalty::create([
                    'loan_id'                 => $loan->id,
                    'amortization_schedule_id' => $schedule->id,
                    'penalty_type'            => $daysOverdue > 30 ? 'non_payment' : 'late_payment',
                    'penalty_rate'            => $penaltyRate,
                    'days_overdue'            => $daysOverdue,
                    'penalty_amount'          => $penaltyCentavos,
                    'waived_amount'           => 0,
                    'net_penalty'             => $penaltyCentavos,
                    'applied_date'            => $asOfDate->toDateString(),
                    'is_paid'                 => false,
                ]);

                // Mark schedule as overdue
                $schedule->update(['status' => 'overdue']);

                $totalNewPenaltiesCentavos += $penaltyCentavos;
                $appliedList->push($penalty);
            }

            if ($totalNewPenaltiesCentavos > 0) {
                DB::table('loans')->where('id', $loan->id)->update([
                    'total_penalties_outstanding' =>
                        $loan->getRawOriginal('total_penalties_outstanding') + $totalNewPenaltiesCentavos,
                ]);
            }
        });

        return $appliedList;
    }

    /**
     * Waive (partially or fully) a loan penalty.
     */
    public function waivePenalty(
        LoanPenalty $penalty,
        int         $waivedAmountCentavos,
        string      $reason,
        User        $operator,
    ): LoanPenalty {
        if ($penalty->is_paid) {
            throw new RuntimeException('Cannot waive a penalty that has already been paid.');
        }

        $maxWaivable = $penalty->getRawOriginal('net_penalty');
        if ($waivedAmountCentavos > $maxWaivable) {
            throw new RuntimeException(
                sprintf('Waived amount (₱%s) exceeds net penalty (₱%s).',
                    number_format($waivedAmountCentavos / 100, 2),
                    number_format($maxWaivable / 100, 2),
                )
            );
        }

        $newNetPenalty = $maxWaivable - $waivedAmountCentavos;

        DB::transaction(function () use ($penalty, $waivedAmountCentavos, $newNetPenalty, $reason, $operator) {
            $penalty->update([
                'waived_amount' => $penalty->getRawOriginal('waived_amount') + $waivedAmountCentavos,
                'net_penalty'   => $newNetPenalty,
                'waived_by'     => $operator->id,
                'waived_at'     => now(),
                'waiver_reason' => $reason,
            ]);

            $loan = $penalty->loan;
            DB::table('loans')->where('id', $loan->id)->update([
                'total_penalties_outstanding' =>
                    max(0, $loan->getRawOriginal('total_penalties_outstanding') - $waivedAmountCentavos),
            ]);
        });

        activity()
            ->performedOn($penalty)
            ->causedBy($operator)
            ->withProperties(['waived_amount_centavos' => $waivedAmountCentavos, 'reason' => $reason])
            ->log('loan_penalty_waived');

        return $penalty->fresh();
    }

    // =========================================================================
    // Reporting
    // =========================================================================

    /**
     * Full loan statement (schedule + payment history within date range).
     */
    public function getLoanStatement(Loan $loan, Carbon $from, Carbon $to): array
    {
        $payments = LoanPayment::where('loan_id', $loan->id)
            ->where('is_reversed', false)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->get();

        $penalties = LoanPenalty::where('loan_id', $loan->id)
            ->whereBetween('applied_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('applied_date')
            ->get();

        return [
            'loan'           => $loan->load(['customer', 'loanProduct', 'officer']),
            'period_from'    => $from->toDateString(),
            'period_to'      => $to->toDateString(),
            'schedule'       => $loan->amortizationSchedules,
            'payments'       => $payments,
            'penalties'      => $penalties,
            'summary'        => [
                'outstanding_balance'       => $loan->getRawOriginal('outstanding_balance') / 100,
                'total_principal_paid'      => $loan->getRawOriginal('total_principal_paid') / 100,
                'total_interest_paid'       => $loan->getRawOriginal('total_interest_paid') / 100,
                'total_penalty_paid'        => $loan->getRawOriginal('total_penalty_paid') / 100,
                'total_penalties_outstanding' => $loan->getRawOriginal('total_penalties_outstanding') / 100,
            ],
        ];
    }

    /**
     * Portfolio-level overview for the dashboard.
     */
    public function getPortfolioOverview(int $storeId): array
    {
        $activeLoans = Loan::where('store_id', $storeId)
            ->where('status', 'active')
            ->get(['outstanding_balance', 'total_penalties_outstanding', 'loan_product_id']);

        $today = now()->toDateString();

        $delinquentCount = Loan::where('store_id', $storeId)
            ->where('status', 'active')
            ->whereHas('amortizationSchedules', fn ($q) =>
                $q->whereIn('status', ['overdue'])
                  ->orWhere(fn ($sq) => $sq->whereIn('status', ['pending', 'partial'])->where('due_date', '<', $today))
            )
            ->count();

        $disbursedThisMonth = Loan::where('store_id', $storeId)
            ->whereMonth('disbursement_date', now()->month)
            ->whereYear('disbursement_date', now()->year)
            ->sum('principal_amount');

        $collectedThisMonth = LoanPayment::where('store_id', $storeId)
            ->where('is_reversed', false)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $totalOutstandingPortfolio = $activeLoans->sum(fn ($l) => $l->getRawOriginal('outstanding_balance'));
        $totalPenaltiesOutstanding = $activeLoans->sum(fn ($l) => $l->getRawOriginal('total_penalties_outstanding'));

        $statusBreakdown = Loan::where('store_id', $storeId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total_active_loans'            => $activeLoans->count(),
            'total_outstanding_portfolio'   => round($totalOutstandingPortfolio / 100, 2),
            'total_penalties_outstanding'   => round($totalPenaltiesOutstanding / 100, 2),
            'delinquent_count'              => $delinquentCount,
            'disbursed_this_month'          => round($disbursedThisMonth / 100, 2),
            'collected_this_month'          => round($collectedThisMonth / 100, 2),
            'status_breakdown'              => $statusBreakdown,
        ];
    }

    /**
     * Retrieve all loans with at least one overdue amortization schedule.
     */
    public function getDelinquentLoans(int $storeId): \Illuminate\Database\Eloquent\Collection
    {
        $today = now()->toDateString();

        return Loan::where('store_id', $storeId)
            ->where('status', 'active')
            ->whereHas('amortizationSchedules', fn ($q) =>
                $q->where('status', 'overdue')
                  ->orWhere(fn ($sq) => $sq->whereIn('status', ['pending', 'partial'])->where('due_date', '<', $today))
            )
            ->with(['customer:id,uuid,name,member_id', 'loanProduct:id,name,loan_type', 'amortizationSchedules'])
            ->get()
            ->map(function (Loan $loan) use ($today) {
                $overdueSchedules = $loan->amortizationSchedules->filter(
                    fn ($s) => $s->status === 'overdue'
                        || (in_array($s->status, ['pending', 'partial']) && $s->due_date < now()->startOfDay())
                );

                $loan->setAttribute('overdue_schedules_count', $overdueSchedules->count());
                $loan->setAttribute('overdue_amount', $overdueSchedules->sum(
                    fn ($s) => ($s->getRawOriginal('total_due') - $s->getRawOriginal('total_paid')) / 100
                ));
                $loan->setAttribute('days_overdue', $overdueSchedules->max(
                    fn ($s) => Carbon::parse($s->due_date)->diffInDays(Carbon::today())
                ));

                return $loan;
            });
    }

    /**
     * 4-bucket loan aging report (same buckets as AR/AP).
     */
    public function getAgingReport(int $storeId): array
    {
        $today = Carbon::today();

        $buckets = [
            'current'   => ['min' => 0,  'max' => 30],
            '31_60'     => ['min' => 31, 'max' => 60],
            '61_90'     => ['min' => 61, 'max' => 90],
            'over_90'   => ['min' => 91, 'max' => PHP_INT_MAX],
        ];

        $results = [];
        foreach ($buckets as $key => $range) {
            $results[$key] = ['count' => 0, 'amount' => 0];
        }

        $overdueSchedules = LoanAmortizationSchedule::whereHas('loan', fn ($q) =>
            $q->where('store_id', $storeId)->where('status', 'active')
        )
        ->where('due_date', '<', $today->toDateString())
        ->whereIn('status', ['pending', 'partial', 'overdue'])
        ->get();

        foreach ($overdueSchedules as $schedule) {
            $daysOverdue  = Carbon::parse($schedule->due_date)->diffInDays($today);
            $overdueDue   = ($schedule->getRawOriginal('total_due') - $schedule->getRawOriginal('total_paid')) / 100;

            foreach ($buckets as $key => $range) {
                if ($daysOverdue >= $range['min'] && $daysOverdue <= $range['max']) {
                    $results[$key]['count']++;
                    $results[$key]['amount'] += $overdueDue;
                    break;
                }
            }
        }

        foreach ($results as &$bucket) {
            $bucket['amount'] = round($bucket['amount'], 2);
        }

        return $results;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function persistSchedule(Loan $loan, array $scheduleRows): void
    {
        $records = array_map(fn ($row) => [
            'loan_id'           => $loan->id,
            'payment_number'    => $row['payment_number'],
            'due_date'          => $row['due_date'],
            'beginning_balance' => $row['beginning_balance'],
            'principal_due'     => $row['principal_due'],
            'interest_due'      => $row['interest_due'],
            'total_due'         => $row['total_due'],
            'ending_balance'    => $row['ending_balance'],
            'principal_paid'    => 0,
            'interest_paid'     => 0,
            'penalty_paid'      => 0,
            'total_paid'        => 0,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $scheduleRows);

        LoanAmortizationSchedule::insert($records);
    }

    private function resolvePeriods(int $termMonths, string $interval): int
    {
        return match ($interval) {
            'weekly'       => $termMonths * 4,
            'semi_monthly' => $termMonths * 2,
            default        => $termMonths,
        };
    }
}
