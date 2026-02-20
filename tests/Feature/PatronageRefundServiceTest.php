<?php

namespace Tests\Feature;

use App\Models\PatronageRefundAllocation;
use App\Models\PatronageRefundBatch;
use App\Services\PatronageRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\CreatesFinancialFixtures;
use Tests\TestCase;

/**
 * Feature tests for PatronageRefundService.
 *
 * Covers:
 *   rate_based vs pool_based computation, approve batch,
 *   record distribution, forfeit allocation, approve + disburse workflow.
 *
 * Sales amounts (total_amount) are stored in CENTAVOS.
 */
class PatronageRefundServiceTest extends TestCase
{
    use RefreshDatabase, CreatesFinancialFixtures;

    private PatronageRefundService $service;

    private $store;
    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = new PatronageRefundService();
        $this->store    = $this->createStore();
        $this->operator = $this->createOperator($this->store);

        $this->actingAs($this->operator);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a batch for the 2025 fiscal year.
     * pr_rate: 3 % (stored as 0.030000 via decimal(8,6))
     */
    private function makeDraftBatch(string $method = 'rate_based', float $prRate = 0.03, int $prFundCentavos = 0): PatronageRefundBatch
    {
        return $this->createPatronageBatch($this->store, [
            'computation_method' => $method,
            'pr_rate'            => $prRate,
            'pr_fund'            => $prFundCentavos,
        ]);
    }

    // =========================================================================
    // Rate-Based Computation
    // =========================================================================

    #[Test]
    public function rate_based_compute_creates_allocations_for_each_member(): void
    {
        $member1 = $this->createMember($this->store);
        $member2 = $this->createMember($this->store);

        $this->createCompletedSale($this->store, $member1, 100_000, '2025-06-01'); // ₱1,000
        $this->createCompletedSale($this->store, $member2, 200_000, '2025-09-01'); // ₱2,000

        $batch  = $this->makeDraftBatch('rate_based', 0.03);
        $result = $this->service->computeBatch($batch, $this->operator);

        $this->assertEquals(2, $result['member_count']);
        $this->assertEquals(2, PatronageRefundAllocation::where('batch_id', $batch->id)->count());
    }

    #[Test]
    public function rate_based_allocation_amount_equals_purchases_times_rate(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01'); // ₱1,000 = 100,000 centavos

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);

        // allocation = 100,000 × 0.03 = 3,000 centavos = ₱30
        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();
        $this->assertEquals(3_000, $allocation->getRawOriginal('allocation_amount'));
    }

    #[Test]
    public function rate_based_batch_updates_total_allocated(): void
    {
        $member1 = $this->createMember($this->store);
        $member2 = $this->createMember($this->store);

        $this->createCompletedSale($this->store, $member1, 100_000, '2025-06-01'); // ₱1,000
        $this->createCompletedSale($this->store, $member2, 200_000, '2025-06-01'); // ₱2,000

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();

        // Total allocated = (100,000 + 200,000) × 0.03 = 9,000 centavos = ₱90
        $this->assertEquals(9_000, $batch->getRawOriginal('total_allocated'));
    }

    #[Test]
    public function rate_based_recompute_wipes_existing_allocations(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);

        $this->service->computeBatch($batch, $this->operator);
        $this->assertEquals(1, PatronageRefundAllocation::where('batch_id', $batch->id)->count());

        // Add another sale and recompute — should replace, not accumulate
        $member2 = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member2, 150_000, '2025-08-01');

        $this->service->computeBatch($batch, $this->operator);
        $this->assertEquals(2, PatronageRefundAllocation::where('batch_id', $batch->id)->count());
    }

    // =========================================================================
    // Pool-Based Computation
    // =========================================================================

    #[Test]
    public function pool_based_total_allocation_equals_pr_fund(): void
    {
        $member1 = $this->createMember($this->store);
        $member2 = $this->createMember($this->store);

        // Member 1: ₱1,000 purchases (100,000 centavos)
        $this->createCompletedSale($this->store, $member1, 100_000, '2025-03-01');
        // Member 2: ₱3,000 purchases (300,000 centavos)
        $this->createCompletedSale($this->store, $member2, 300_000, '2025-03-01');

        $prFund = 50_000; // ₱500 pool
        $batch  = $this->makeDraftBatch('pool_based', 0, $prFund);
        $this->service->computeBatch($batch, $this->operator);

        // Total allocated should equal prFund (within 1 centavo rounding)
        $totalAllocated = PatronageRefundAllocation::where('batch_id', $batch->id)
            ->sum('allocation_amount');

        $this->assertEqualsWithDelta($prFund, $totalAllocated, 1,
            'Pool-based: total allocations must equal the PR fund amount');
    }

    #[Test]
    public function pool_based_allocation_proportional_to_purchases(): void
    {
        $member1 = $this->createMember($this->store);
        $member2 = $this->createMember($this->store);

        // Member 1: 25 % of purchases
        $this->createCompletedSale($this->store, $member1, 25_000, '2025-01-15');
        // Member 2: 75 % of purchases
        $this->createCompletedSale($this->store, $member2, 75_000, '2025-01-15');

        $prFund = 100_000; // ₱1,000 pool (centavos)
        $batch  = $this->makeDraftBatch('pool_based', 0, $prFund);
        $this->service->computeBatch($batch, $this->operator);

        $allocs = PatronageRefundAllocation::where('batch_id', $batch->id)
            ->orderBy('member_purchases')
            ->get();

        // Member 1 (25 %): 25,000 centavos
        $this->assertEqualsWithDelta(25_000, $allocs[0]->getRawOriginal('allocation_amount'), 1);
        // Member 2 (75 %): 75,000 centavos
        $this->assertEqualsWithDelta(75_000, $allocs[1]->getRawOriginal('allocation_amount'), 1);
    }

    // =========================================================================
    // Empty Period
    // =========================================================================

    #[Test]
    public function compute_batch_with_no_sales_returns_zero_counts(): void
    {
        $batch  = $this->makeDraftBatch('rate_based', 0.03);
        $result = $this->service->computeBatch($batch, $this->operator);

        $this->assertEquals(0, $result['member_count']);
        $this->assertEquals('0.00', $result['total_allocated']);
    }

    #[Test]
    public function compute_batch_throws_when_not_in_draft_status(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch();
        $this->service->computeBatch($batch, $this->operator);
        $batch->update(['member_count' => 1]); // ensure member_count > 0
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only draft batches can be (re)computed');

        $this->service->computeBatch($batch, $this->operator);
    }

    // =========================================================================
    // Approve Batch
    // =========================================================================

    #[Test]
    public function approve_batch_changes_status_to_approved(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch();
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();

        $approved = $this->service->approveBatch($batch, [], $this->operator);

        $this->assertEquals('approved', $approved->status);
    }

    #[Test]
    public function approve_batch_throws_when_member_count_is_zero(): void
    {
        $batch = $this->makeDraftBatch(); // no sales → member_count = 0

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot approve a batch with no allocations');

        $this->service->approveBatch($batch, [], $this->operator);
    }

    #[Test]
    public function approve_batch_throws_when_not_draft(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch();
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only draft batches can be approved');

        $this->service->approveBatch($batch, [], $this->operator);
    }

    // =========================================================================
    // Approve + Disburse Workflow
    // =========================================================================

    #[Test]
    public function full_approve_and_disburse_workflow_completes_batch(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();

        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();

        $paid = $this->service->recordDistribution($allocation, [
            'payment_method' => 'cash',
            'paid_date'      => now()->toDateString(),
        ], $this->operator);

        $this->assertEquals('paid', $paid->status);

        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
    }

    #[Test]
    public function record_distribution_increments_customer_accumulated_patronage(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();
        $allocAmount = $allocation->getRawOriginal('allocation_amount');

        $this->service->recordDistribution($allocation, [
            'payment_method' => 'cash',
            'paid_date'      => now()->toDateString(),
        ], $this->operator);

        $member->refresh();
        $this->assertEquals($allocAmount, $member->getRawOriginal('accumulated_patronage'));
    }

    #[Test]
    public function batch_status_changes_to_distributing_when_some_paid(): void
    {
        $member1 = $this->createMember($this->store);
        $member2 = $this->createMember($this->store);

        $this->createCompletedSale($this->store, $member1, 100_000, '2025-06-01');
        $this->createCompletedSale($this->store, $member2, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocations = PatronageRefundAllocation::where('batch_id', $batch->id)->get();

        // Pay only the first allocation
        $this->service->recordDistribution($allocations->first(), [
            'payment_method' => 'cash',
            'paid_date'      => now()->toDateString(),
        ], $this->operator);

        $batch->refresh();
        $this->assertEquals('distributing', $batch->status);
    }

    #[Test]
    public function record_distribution_throws_for_non_pending_allocation(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();
        $this->service->recordDistribution($allocation, [
            'payment_method' => 'cash',
            'paid_date'      => now()->toDateString(),
        ], $this->operator);

        $allocation->refresh();
        $this->assertEquals('paid', $allocation->status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending allocations can be marked as paid');

        $this->service->recordDistribution($allocation, [
            'payment_method' => 'cash',
            'paid_date'      => now()->toDateString(),
        ], $this->operator);
    }

    // =========================================================================
    // Forfeit Allocation
    // =========================================================================

    #[Test]
    public function forfeit_allocation_sets_status_to_forfeited(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();
        $forfeited  = $this->service->forfeitAllocation($allocation, [
            'notes' => 'Member unreachable.',
        ], $this->operator);

        $this->assertEquals('forfeited', $forfeited->status);
    }

    #[Test]
    public function forfeit_all_allocations_completes_the_batch(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocations = PatronageRefundAllocation::where('batch_id', $batch->id)->get();
        foreach ($allocations as $alloc) {
            $this->service->forfeitAllocation($alloc, ['notes' => 'Forfeited.'], $this->operator);
        }

        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
    }

    #[Test]
    public function forfeit_throws_for_non_pending_allocation(): void
    {
        $member = $this->createMember($this->store);
        $this->createCompletedSale($this->store, $member, 100_000, '2025-06-01');

        $batch = $this->makeDraftBatch('rate_based', 0.03);
        $this->service->computeBatch($batch, $this->operator);
        $batch->refresh();
        $this->service->approveBatch($batch, [], $this->operator);
        $batch->refresh();

        $allocation = PatronageRefundAllocation::where('batch_id', $batch->id)->first();
        $this->service->forfeitAllocation($allocation, ['notes' => 'First forfeit.'], $this->operator);
        $allocation->refresh();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only pending allocations can be forfeited');

        $this->service->forfeitAllocation($allocation, ['notes' => 'Duplicate.'], $this->operator);
    }
}
