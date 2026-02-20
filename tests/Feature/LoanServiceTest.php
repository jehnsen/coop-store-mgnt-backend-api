<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanAmortizationSchedule;
use App\Models\LoanPayment;
use App\Models\LoanPenalty;
use App\Services\AmortizationService;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\CreatesFinancialFixtures;
use Tests\TestCase;

/**
 * Feature tests for LoanService.
 *
 * Covers the full loan lifecycle:
 *   Apply → Approve → Disburse → Payments (FIFO) → Reversal → Closure → Penalties
 *
 * Monetary inputs to LoanService follow the controller convention:
 *   principal_amount is passed in CENTAVOS (controller already converts from pesos).
 *   payment amount is passed in CENTAVOS.
 */
class LoanServiceTest extends TestCase
{
    use RefreshDatabase, CreatesFinancialFixtures;

    private LoanService $service;

    // Shared fixtures (set up per-test via setUp)
    private $store;
    private $operator;
    private $member;
    private $loanProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoanService(new AmortizationService());

        $this->store       = $this->createStore();
        $this->operator    = $this->createOperator($this->store);
        $this->member      = $this->createMember($this->store);
        $this->loanProduct = $this->createLoanProduct($this->store, [
            'interest_rate'       => 0.015,
            'processing_fee_rate' => 0.0,
            'service_fee'         => 0,
        ]);

        // Authenticate so BelongsToStore global scope works
        $this->actingAs($this->operator);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Apply a simple ₱10,000 monthly loan and return the Loan model.
     */
    private function applySimpleLoan(array $overrides = []): Loan
    {
        return $this->service->applyLoan(array_merge([
            'customer_uuid'      => $this->member->uuid,
            'loan_product_uuid'  => $this->loanProduct->uuid,
            'principal_amount'   => 1_000_000,  // ₱10,000 in centavos
            'term_months'        => 12,
            'payment_interval'   => 'monthly',
            'purpose'            => 'Business capital',
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ], $overrides), $this->operator);
    }

    // =========================================================================
    // Apply Loan
    // =========================================================================

    #[Test]
    public function apply_loan_creates_pending_loan_record(): void
    {
        $loan = $this->applySimpleLoan();

        $this->assertDatabaseHas('loans', [
            'id'     => $loan->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function apply_loan_generates_unique_loan_number(): void
    {
        $loan = $this->applySimpleLoan();

        $this->assertMatchesRegularExpression('/^LN-\d{4}-\d{6}$/', $loan->loan_number);
    }

    #[Test]
    public function apply_loan_persists_full_amortization_schedule(): void
    {
        $loan = $this->applySimpleLoan(['term_months' => 12]);

        $scheduleCount = LoanAmortizationSchedule::where('loan_id', $loan->id)->count();

        $this->assertEquals(12, $scheduleCount);
    }

    #[Test]
    public function apply_loan_schedule_all_rows_start_as_pending(): void
    {
        $loan = $this->applySimpleLoan();

        $nonPending = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->where('status', '!=', 'pending')
            ->count();

        $this->assertEquals(0, $nonPending);
    }

    #[Test]
    public function apply_loan_throws_for_non_member_customer(): void
    {
        $nonMember = $this->createNonMember($this->store);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only cooperative members may apply');

        $this->service->applyLoan([
            'customer_uuid'      => $nonMember->uuid,
            'loan_product_uuid'  => $this->loanProduct->uuid,
            'principal_amount'   => 1_000_000,
            'term_months'        => 12,
            'payment_interval'   => 'monthly',
            'purpose'            => 'Test',
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->operator);
    }

    #[Test]
    public function apply_loan_with_weekly_interval_creates_48_periods_for_12_months(): void
    {
        $loan = $this->applySimpleLoan(['term_months' => 12, 'payment_interval' => 'weekly']);

        $this->assertEquals(
            48,
            LoanAmortizationSchedule::where('loan_id', $loan->id)->count()
        );
    }

    #[Test]
    public function apply_loan_with_semi_monthly_interval_creates_24_periods_for_12_months(): void
    {
        $loan = $this->applySimpleLoan(['term_months' => 12, 'payment_interval' => 'semi_monthly']);

        $this->assertEquals(
            24,
            LoanAmortizationSchedule::where('loan_id', $loan->id)->count()
        );
    }

    // =========================================================================
    // Approve Loan
    // =========================================================================

    #[Test]
    public function approve_loan_changes_status_to_approved(): void
    {
        $loan = $this->applySimpleLoan();

        $approved = $this->service->approveLoan($loan, $this->operator);

        $this->assertEquals('approved', $approved->status);
        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'status' => 'approved']);
    }

    #[Test]
    public function approve_loan_records_approver_and_date(): void
    {
        $loan = $this->applySimpleLoan();

        $this->service->approveLoan($loan, $this->operator, [
            'approval_date' => '2026-02-20',
        ]);

        $this->assertDatabaseHas('loans', [
            'id'           => $loan->id,
            'approved_by'  => $this->operator->id,
            'approval_date' => '2026-02-20',
        ]);
    }

    #[Test]
    public function approve_loan_throws_when_already_approved(): void
    {
        $loan = $this->applySimpleLoan();
        $this->service->approveLoan($loan, $this->operator);
        $loan->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot approve a loan with status 'approved'");

        $this->service->approveLoan($loan, $this->operator);
    }

    #[Test]
    public function approve_loan_throws_for_rejected_loan(): void
    {
        $loan = $this->applySimpleLoan();
        $this->service->rejectLoan($loan, $this->operator, 'Test rejection');
        $loan->refresh();

        $this->expectException(RuntimeException::class);

        $this->service->approveLoan($loan, $this->operator);
    }

    // =========================================================================
    // Reject Loan
    // =========================================================================

    #[Test]
    public function reject_loan_changes_status_to_rejected(): void
    {
        $loan = $this->applySimpleLoan();

        $rejected = $this->service->rejectLoan($loan, $this->operator, 'Insufficient income.');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Insufficient income.', $rejected->rejection_reason);
    }

    #[Test]
    public function reject_loan_throws_when_not_pending(): void
    {
        $loan = $this->applySimpleLoan();
        $this->service->approveLoan($loan, $this->operator);
        $loan->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot reject a loan with status 'approved'");

        $this->service->rejectLoan($loan, $this->operator, 'Late rejection');
    }

    // =========================================================================
    // Disburse Loan
    // =========================================================================

    #[Test]
    public function disburse_loan_changes_status_to_active(): void
    {
        $loan = $this->applySimpleLoan();
        $this->service->approveLoan($loan, $this->operator);
        $loan->refresh();

        $disbursed = $this->service->disburseLoan($loan, $this->operator, [
            'disbursement_date' => '2026-02-20',
        ]);

        $this->assertEquals('active', $disbursed->status);
    }

    #[Test]
    public function disburse_loan_records_disbursement_date_and_maturity(): void
    {
        $loan = $this->applySimpleLoan(['term_months' => 12]);
        $this->service->approveLoan($loan, $this->operator);
        $loan->refresh();

        $disbursed = $this->service->disburseLoan($loan, $this->operator, [
            'disbursement_date'  => '2026-02-20',
            'first_payment_date' => '2026-03-01',
        ]);

        $this->assertDatabaseHas('loans', [
            'id'                => $loan->id,
            'disbursement_date' => '2026-02-20',
        ]);
        $this->assertNotNull($disbursed->maturity_date);
    }

    #[Test]
    public function disburse_loan_throws_when_not_approved(): void
    {
        $loan = $this->applySimpleLoan(); // status = pending

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot disburse a loan with status 'pending'");

        $this->service->disburseLoan($loan, $this->operator, [
            'disbursement_date' => '2026-02-20',
        ]);
    }

    // =========================================================================
    // Payment Recording — FIFO Allocation
    // =========================================================================

    /** Helper: apply → approve → disburse a loan and return the active Loan. */
    private function getActiveLoan(array $applyOverrides = []): Loan
    {
        $loan = $this->applySimpleLoan($applyOverrides);
        $this->service->approveLoan($loan, $this->operator);
        $loan->refresh();
        $this->service->disburseLoan($loan, $this->operator, [
            'disbursement_date'  => now()->toDateString(),
            'first_payment_date' => now()->addMonth()->startOfMonth()->toDateString(),
        ]);
        return $loan->fresh();
    }

    #[Test]
    public function record_payment_creates_payment_ledger_entry(): void
    {
        $loan = $this->getActiveLoan();

        $payment = $this->service->recordPayment($loan, [
            'amount'         => 91_684,  // centavos ≈ one EMI
            'payment_method' => 'cash',
            'payment_date'   => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->operator);

        $this->assertDatabaseHas('loan_payments', ['id' => $payment->id]);
    }

    #[Test]
    public function record_payment_reduces_outstanding_balance(): void
    {
        $loan    = $this->getActiveLoan();
        $balBefore = $loan->getRawOriginal('outstanding_balance');

        $this->service->recordPayment($loan, [
            'amount'         => 91_684,
            'payment_method' => 'cash',
            'payment_date'   => now()->addMonth()->startOfMonth()->toDateString(),
        ], $this->operator);

        $loan->refresh();
        $this->assertLessThan($balBefore, $loan->getRawOriginal('outstanding_balance'));
    }

    #[Test]
    public function record_payment_allocates_to_oldest_schedule_first_fifo(): void
    {
        $loan = $this->getActiveLoan();

        // Manually make two schedules overdue
        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->take(2)
            ->update(['due_date' => now()->subMonths(2)->toDateString()]);

        // Pay enough to cover the first schedule's total_due
        $firstSchedule = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first();
        $firstDue = $firstSchedule->getRawOriginal('total_due');

        $this->service->recordPayment($loan, [
            'amount'         => $firstDue,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        // First schedule should now be fully paid
        $firstSchedule->refresh();
        $this->assertEquals('paid', $firstSchedule->status);

        // Second schedule should still be pending/overdue (not touched)
        $secondSchedule = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->skip(1)
            ->first();
        $this->assertContains($secondSchedule->status, ['pending', 'overdue', 'partial']);
    }

    #[Test]
    public function record_payment_applies_penalties_before_principal_and_interest(): void
    {
        $loan = $this->getActiveLoan();

        // Force a past-due schedule so we can compute a penalty
        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());
        $this->assertGreaterThan(0, $penalties->count());

        $loan->refresh();
        $totalPenaltyBefore = $loan->getRawOriginal('total_penalties_outstanding');

        // Pay just enough to cover penalties
        $this->service->recordPayment($loan, [
            'amount'         => $totalPenaltyBefore,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $loan->refresh();
        // Penalties outstanding should now be zero
        $this->assertEquals(0, $loan->getRawOriginal('total_penalties_outstanding'));
        // penalty_paid should be > 0
        $this->assertGreaterThan(0, $loan->getRawOriginal('total_penalty_paid'));
    }

    #[Test]
    public function loan_closes_when_fully_paid(): void
    {
        $loan = $this->getActiveLoan([
            'principal_amount' => 1_000_000,
            'term_months'      => 1,
        ]);

        // Get the single schedule row's total_due
        $schedule = LoanAmortizationSchedule::where('loan_id', $loan->id)->first();
        $totalDue = $schedule->getRawOriginal('total_due');

        $this->service->recordPayment($loan, [
            'amount'         => $totalDue,
            'payment_method' => 'cash',
            'payment_date'   => now()->addMonth()->toDateString(),
        ], $this->operator);

        $loan->refresh();
        $this->assertEquals('closed', $loan->status);
    }

    #[Test]
    public function record_payment_throws_when_loan_is_pending(): void
    {
        $loan = $this->applySimpleLoan(); // pending, not active

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot record a payment on a loan with status 'pending'");

        $this->service->recordPayment($loan, [
            'amount'         => 91_684,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);
    }

    // =========================================================================
    // Payment Reversal
    // =========================================================================

    #[Test]
    public function reverse_payment_marks_payment_as_reversed(): void
    {
        $loan = $this->getActiveLoan();

        $payment = $this->service->recordPayment($loan, [
            'amount'         => 91_684,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $reversed = $this->service->reversePayment($payment, $this->operator);

        $this->assertTrue($reversed->is_reversed);
        $this->assertNotNull($reversed->reversed_at);
        $this->assertEquals($this->operator->id, $reversed->reversed_by);
    }

    #[Test]
    public function reverse_payment_restores_outstanding_balance(): void
    {
        $loan      = $this->getActiveLoan();
        $balBefore = $loan->getRawOriginal('outstanding_balance');

        $payment = $this->service->recordPayment($loan, [
            'amount'         => 91_684,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $loan->refresh();
        $balAfterPayment = $loan->getRawOriginal('outstanding_balance');
        $this->assertLessThan($balBefore, $balAfterPayment);

        $this->service->reversePayment($payment, $this->operator);

        $loan->refresh();
        $balAfterReversal = $loan->getRawOriginal('outstanding_balance');
        $this->assertEquals($balBefore, $balAfterReversal);
    }

    #[Test]
    public function reverse_payment_reopens_a_closed_loan(): void
    {
        $loan = $this->getActiveLoan([
            'principal_amount' => 1_000_000,
            'term_months'      => 1,
        ]);

        $schedule = LoanAmortizationSchedule::where('loan_id', $loan->id)->first();
        $totalDue = $schedule->getRawOriginal('total_due');

        $payment = $this->service->recordPayment($loan, [
            'amount'         => $totalDue,
            'payment_method' => 'cash',
            'payment_date'   => now()->addMonth()->toDateString(),
        ], $this->operator);

        $loan->refresh();
        $this->assertEquals('closed', $loan->status);

        $this->service->reversePayment($payment, $this->operator);

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
    }

    #[Test]
    public function reverse_payment_throws_when_already_reversed(): void
    {
        $loan = $this->getActiveLoan();

        $payment = $this->service->recordPayment($loan, [
            'amount'         => 91_684,
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $this->service->reversePayment($payment, $this->operator);
        $payment->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This payment has already been reversed');

        $this->service->reversePayment($payment, $this->operator);
    }

    // =========================================================================
    // Penalty Computation
    // =========================================================================

    #[Test]
    public function compute_penalties_creates_penalty_for_overdue_schedule(): void
    {
        $loan = $this->getActiveLoan();

        // Force first schedule to be overdue by 30 days
        $schedule = LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first();
        $schedule->update(['due_date' => now()->subDays(30)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());

        $this->assertGreaterThan(0, $penalties->count());
        $this->assertDatabaseHas('loan_penalties', ['loan_id' => $loan->id]);
    }

    #[Test]
    public function compute_penalties_updates_loan_outstanding_penalty_total(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $this->service->computePenalties($loan, Carbon::today());

        $loan->refresh();
        $this->assertGreaterThan(0, $loan->getRawOriginal('total_penalties_outstanding'));
    }

    #[Test]
    public function compute_penalties_skips_if_no_overdue_schedules(): void
    {
        $loan = $this->getActiveLoan();

        // All schedules are in the future — no overdue
        $penalties = $this->service->computePenalties($loan, Carbon::today());

        $this->assertCount(0, $penalties);
        $this->assertEquals(0, $loan->getRawOriginal('total_penalties_outstanding'));
    }

    #[Test]
    public function compute_penalties_does_not_create_duplicate_for_same_date(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $today = Carbon::today();
        $this->service->computePenalties($loan, $today);
        $this->service->computePenalties($loan, $today); // second call

        $penaltyCount = LoanPenalty::where('loan_id', $loan->id)
            ->where('applied_date', $today->toDateString())
            ->count();

        $this->assertEquals(1, $penaltyCount, 'No duplicate penalty for same date');
    }

    #[Test]
    public function compute_penalties_throws_on_non_active_loan(): void
    {
        $loan = $this->applySimpleLoan(); // pending

        $this->expectException(RuntimeException::class);

        $this->service->computePenalties($loan, Carbon::today());
    }

    #[Test]
    public function late_payment_penalty_type_for_under_30_days_overdue(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(15)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());

        $this->assertEquals('late_payment', $penalties->first()->penalty_type);
    }

    #[Test]
    public function non_payment_penalty_type_for_over_30_days_overdue(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(45)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());

        $this->assertEquals('non_payment', $penalties->first()->penalty_type);
    }

    // =========================================================================
    // Waive Penalty
    // =========================================================================

    #[Test]
    public function waive_penalty_reduces_net_penalty(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());
        $penalty   = $penalties->first();
        $loan->refresh();

        $originalNet = $penalty->getRawOriginal('net_penalty');
        $waivedAmount = (int) round($originalNet * 0.5); // waive half

        $updated = $this->service->waivePenalty(
            $penalty,
            $waivedAmount,
            'Goodwill waiver',
            $this->operator
        );

        $this->assertEquals($originalNet - $waivedAmount, $updated->getRawOriginal('net_penalty'));
        $this->assertEquals($waivedAmount, $updated->getRawOriginal('waived_amount'));
        $this->assertEquals('Goodwill waiver', $updated->waiver_reason);
    }

    #[Test]
    public function waive_penalty_decrements_loan_outstanding_penalties(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());
        $loan->refresh();
        $penalty       = $penalties->first();
        $netPenalty    = $penalty->getRawOriginal('net_penalty');
        $totalBefore   = $loan->getRawOriginal('total_penalties_outstanding');

        $this->service->waivePenalty($penalty, $netPenalty, 'Full waiver', $this->operator);

        $loan->refresh();
        $this->assertEquals($totalBefore - $netPenalty, $loan->getRawOriginal('total_penalties_outstanding'));
    }

    #[Test]
    public function waive_penalty_throws_when_waived_amount_exceeds_net_penalty(): void
    {
        $loan = $this->getActiveLoan();

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->orderBy('payment_number')
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());
        $penalty   = $penalties->first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Waived amount');

        $this->service->waivePenalty(
            $penalty,
            $penalty->getRawOriginal('net_penalty') + 1, // 1 centavo over
            'Over-waive attempt',
            $this->operator
        );
    }

    #[Test]
    public function waive_penalty_throws_when_already_paid(): void
    {
        $loan = $this->getActiveLoan([
            'principal_amount' => 1_000_000,
            'term_months'      => 1,
        ]);

        LoanAmortizationSchedule::where('loan_id', $loan->id)
            ->first()
            ->update(['due_date' => now()->subDays(35)->toDateString()]);

        $penalties = $this->service->computePenalties($loan, Carbon::today());
        $penalty   = $penalties->first();
        $loan->refresh();

        // Pay enough to cover penalties + schedule
        $totalDue = LoanAmortizationSchedule::where('loan_id', $loan->id)->first()->getRawOriginal('total_due');
        $this->service->recordPayment($loan, [
            'amount'         => $totalDue + $loan->getRawOriginal('total_penalties_outstanding'),
            'payment_method' => 'cash',
            'payment_date'   => now()->toDateString(),
        ], $this->operator);

        $penalty->refresh();
        $this->assertTrue($penalty->is_paid);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot waive a penalty that has already been paid');

        $this->service->waivePenalty($penalty, 100, 'Too late', $this->operator);
    }
}
