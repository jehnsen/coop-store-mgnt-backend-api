<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PatronageRefundAllocation;
use App\Models\PatronageRefundBatch;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatronageRefundService
{
    /**
     * Compute (or recompute) patronage refund allocations for a draft batch.
     *
     * Pulls all completed sales for member customers in the batch period,
     * then allocates according to computation_method:
     *   - rate_based  : allocation = member_purchases × pr_rate
     *   - pool_based  : allocation = (member_purchases / total_member_purchases) × pr_fund
     *
     * Existing pending allocations are wiped and replaced; the batch totals
     * are refreshed. The batch must be in 'draft' status.
     *
     * @return array Summary of computation results.
     */
    public function computeBatch(PatronageRefundBatch $batch, User $operator): array
    {
        if ($batch->status !== 'draft') {
            throw new \RuntimeException('Only draft batches can be (re)computed.');
        }

        return DB::transaction(function () use ($batch, $operator) {

            // 1. Delete any existing (pending) allocations
            PatronageRefundAllocation::where('batch_id', $batch->id)->delete();

            // 2. Fetch per-member purchase totals (centavos) for the period
            $from = Carbon::parse($batch->period_from)->startOfDay();
            $to   = Carbon::parse($batch->period_to)->endOfDay();

            $rows = Sale::where('store_id', $batch->store_id)
                ->where('status', 'completed')
                ->whereNotNull('customer_id')
                ->whereHas('customer', fn ($q) => $q->where('is_member', true))
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('customer_id, SUM(total_amount) as purchases')
                ->groupBy('customer_id')
                ->get();

            if ($rows->isEmpty()) {
                // Update batch with zero totals
                $batch->update([
                    'total_member_purchases' => 0,
                    'total_store_sales'      => 0,
                    'total_allocated'        => 0,
                    'member_count'           => 0,
                ]);

                return [
                    'member_count'            => 0,
                    'total_member_purchases'  => '0.00',
                    'total_allocated'         => '0.00',
                    'message'                 => 'No completed sales found for member customers in the period.',
                ];
            }

            // 3. Total store sales (all statuses = completed for the period)
            $totalStoreSales = (int) Sale::where('store_id', $batch->store_id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$from, $to])
                ->sum('total_amount');

            // 4. Aggregate member totals
            $totalMemberPurchases = (int) $rows->sum('purchases');

            // 5. Determine allocation per method
            $prRate   = (float) ($batch->getRawOriginal('pr_rate') / 1_000_000); // e.g. 0.030000
            $prFund   = (int)   $batch->getRawOriginal('pr_fund');              // centavos

            $allocations    = [];
            $totalAllocated = 0;

            foreach ($rows as $row) {
                $memberPurchases = (int) $row->purchases;

                if ($batch->computation_method === 'rate_based') {
                    // allocation = member_purchases × pr_rate
                    $allocationAmount = (int) round($memberPurchases * $prRate);
                    $allocationPct    = $totalMemberPurchases > 0
                        ? round(($memberPurchases / $totalMemberPurchases) * 100, 6)
                        : 0;
                } else {
                    // pool_based: allocation = (member_purchases / total_member_purchases) × pr_fund
                    $allocationPct    = $totalMemberPurchases > 0
                        ? round(($memberPurchases / $totalMemberPurchases) * 100, 6)
                        : 0;
                    $allocationAmount = $totalMemberPurchases > 0
                        ? (int) round(($memberPurchases / $totalMemberPurchases) * $prFund)
                        : 0;
                }

                $totalAllocated += $allocationAmount;

                $allocations[] = [
                    'uuid'                  => \Illuminate\Support\Str::uuid(),
                    'store_id'              => $batch->store_id,
                    'batch_id'              => $batch->id,
                    'customer_id'           => $row->customer_id,
                    'member_purchases'      => $memberPurchases,
                    'allocation_percentage' => $allocationPct,
                    'allocation_amount'     => $allocationAmount,
                    'status'                => 'pending',
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            }

            // 6. Bulk insert allocations
            PatronageRefundAllocation::insert($allocations);

            // 7. Update batch totals
            $batch->update([
                'total_member_purchases' => $totalMemberPurchases,
                'total_store_sales'      => $totalStoreSales,
                'total_allocated'        => $totalAllocated,
                'member_count'           => count($allocations),
            ]);

            activity()
                ->performedOn($batch)
                ->causedBy($operator)
                ->withProperties(['member_count' => count($allocations), 'total_allocated' => $totalAllocated])
                ->log('patronage_refund_batch_computed');

            return [
                'member_count'           => count($allocations),
                'total_member_purchases' => number_format($totalMemberPurchases / 100, 2, '.', ''),
                'total_store_sales'      => number_format($totalStoreSales / 100, 2, '.', ''),
                'total_allocated'        => number_format($totalAllocated / 100, 2, '.', ''),
            ];
        });
    }

    /**
     * Approve a computed (draft) batch, locking it from recomputation.
     */
    public function approveBatch(PatronageRefundBatch $batch, array $data, User $operator): PatronageRefundBatch
    {
        if ($batch->status !== 'draft') {
            throw new \RuntimeException('Only draft batches can be approved.');
        }

        if ($batch->member_count === 0) {
            throw new \RuntimeException('Cannot approve a batch with no allocations. Compute first.');
        }

        return DB::transaction(function () use ($batch, $data, $operator) {
            $batch->update([
                'status'      => 'approved',
                'approved_by' => $operator->id,
                'approved_at' => now(),
                'notes'       => $data['notes'] ?? $batch->notes,
            ]);

            activity()
                ->performedOn($batch)
                ->causedBy($operator)
                ->log('patronage_refund_batch_approved');

            return $batch->fresh();
        });
    }

    /**
     * Record payment/distribution of a single allocation.
     *
     * - Sets allocation status to 'paid'
     * - Increments batch.total_distributed
     * - Increments customer.accumulated_patronage
     * - Sets batch to 'distributing' on first payment; 'completed' when all resolved
     */
    public function recordDistribution(
        PatronageRefundAllocation $allocation,
        array $data,
        User $operator
    ): PatronageRefundAllocation {
        if ($allocation->status !== 'pending') {
            throw new \RuntimeException('Only pending allocations can be marked as paid.');
        }

        $batch = $allocation->batch;

        if (! in_array($batch->status, ['approved', 'distributing'])) {
            throw new \RuntimeException('Distributions can only be recorded for approved or distributing batches.');
        }

        return DB::transaction(function () use ($allocation, $data, $operator, $batch) {
            $allocationAmount = (int) $allocation->getRawOriginal('allocation_amount');

            // Mark allocation as paid
            $allocation->update([
                'status'           => 'paid',
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_date'        => $data['paid_date'] ?? today(),
                'paid_by'          => $operator->id,
                'notes'            => $data['notes'] ?? null,
            ]);

            // Increment batch distributed total
            DB::table('patronage_refund_batches')
                ->where('id', $batch->id)
                ->increment('total_distributed', $allocationAmount);

            // Increment customer accumulated_patronage (lifetime total)
            DB::table('customers')
                ->where('id', $allocation->customer_id)
                ->increment('accumulated_patronage', $allocationAmount);

            // Update batch status
            $resolved = PatronageRefundAllocation::where('batch_id', $batch->id)
                ->whereIn('status', ['paid', 'forfeited'])
                ->count();
            $total = PatronageRefundAllocation::where('batch_id', $batch->id)->count();

            $newBatchStatus = ($resolved >= $total) ? 'completed' : 'distributing';
            $batch->update(['status' => $newBatchStatus]);

            activity()
                ->performedOn($allocation)
                ->causedBy($operator)
                ->withProperties(['allocation_amount' => $allocationAmount, 'payment_method' => $data['payment_method']])
                ->log('patronage_refund_distribution_recorded');

            return $allocation->fresh();
        });
    }

    /**
     * Forfeit a pending allocation (member forfeits their patronage refund).
     */
    public function forfeitAllocation(
        PatronageRefundAllocation $allocation,
        array $data,
        User $operator
    ): PatronageRefundAllocation {
        if ($allocation->status !== 'pending') {
            throw new \RuntimeException('Only pending allocations can be forfeited.');
        }

        $batch = $allocation->batch;

        if (! in_array($batch->status, ['approved', 'distributing'])) {
            throw new \RuntimeException('Allocations can only be forfeited for approved or distributing batches.');
        }

        return DB::transaction(function () use ($allocation, $data, $operator, $batch) {
            $allocation->update([
                'status' => 'forfeited',
                'notes'  => $data['notes'] ?? null,
            ]);

            // Check if all resolved
            $resolved = PatronageRefundAllocation::where('batch_id', $batch->id)
                ->whereIn('status', ['paid', 'forfeited'])
                ->count();
            $total = PatronageRefundAllocation::where('batch_id', $batch->id)->count();

            $newBatchStatus = ($resolved >= $total) ? 'completed' : 'distributing';
            $batch->update(['status' => $newBatchStatus]);

            activity()
                ->performedOn($allocation)
                ->causedBy($operator)
                ->withProperties(['notes' => $data['notes'] ?? null])
                ->log('patronage_refund_allocation_forfeited');

            return $allocation->fresh();
        });
    }

    /**
     * Portfolio overview — batch stats for a store.
     */
    public function getOverview(int $storeId): array
    {
        $batches = PatronageRefundBatch::where('store_id', $storeId)->get();

        $totalBatches     = $batches->count();
        $draftCount       = $batches->where('status', 'draft')->count();
        $approvedCount    = $batches->where('status', 'approved')->count();
        $distributingCount= $batches->where('status', 'distributing')->count();
        $completedCount   = $batches->where('status', 'completed')->count();

        $totalAllocated   = PatronageRefundBatch::where('store_id', $storeId)
            ->sum('total_allocated');
        $totalDistributed = PatronageRefundBatch::where('store_id', $storeId)
            ->sum('total_distributed');

        $pendingAllocs = PatronageRefundAllocation::where('store_id', $storeId)
            ->where('status', 'pending')
            ->count();

        return [
            'total_batches'          => $totalBatches,
            'draft_batches'          => $draftCount,
            'approved_batches'       => $approvedCount,
            'distributing_batches'   => $distributingCount,
            'completed_batches'      => $completedCount,
            'total_allocated'        => number_format($totalAllocated / 100, 2, '.', ''),
            'total_distributed'      => number_format($totalDistributed / 100, 2, '.', ''),
            'pending_distributions'  => $pendingAllocs,
        ];
    }

    /**
     * Detailed summary for a single batch including allocation breakdown.
     */
    public function getBatchSummary(PatronageRefundBatch $batch): array
    {
        $batch->load(['allocations.customer:id,uuid,name,member_id', 'approvedBy:id,name']);

        $allocations = $batch->allocations->map(function ($a) {
            return [
                'uuid'                  => $a->uuid,
                'customer_uuid'         => $a->customer?->uuid,
                'customer_name'         => $a->customer?->name,
                'member_id'             => $a->customer?->member_id,
                'member_purchases'      => number_format($a->getRawOriginal('member_purchases') / 100, 2, '.', ''),
                'allocation_percentage' => $a->allocation_percentage,
                'allocation_amount'     => number_format($a->getRawOriginal('allocation_amount') / 100, 2, '.', ''),
                'status'                => $a->status,
                'payment_method'        => $a->payment_method,
                'reference_number'      => $a->reference_number,
                'paid_date'             => $a->paid_date?->toDateString(),
            ];
        });

        return [
            'batch'       => [
                'uuid'                   => $batch->uuid,
                'period_label'           => $batch->period_label,
                'period_from'            => $batch->period_from?->toDateString(),
                'period_to'              => $batch->period_to?->toDateString(),
                'computation_method'     => $batch->computation_method,
                'pr_rate'                => $batch->pr_rate,
                'pr_fund'                => number_format($batch->getRawOriginal('pr_fund') / 100, 2, '.', ''),
                'total_member_purchases' => number_format($batch->getRawOriginal('total_member_purchases') / 100, 2, '.', ''),
                'total_store_sales'      => number_format($batch->getRawOriginal('total_store_sales') / 100, 2, '.', ''),
                'total_allocated'        => number_format($batch->getRawOriginal('total_allocated') / 100, 2, '.', ''),
                'total_distributed'      => number_format($batch->getRawOriginal('total_distributed') / 100, 2, '.', ''),
                'member_count'           => $batch->member_count,
                'status'                 => $batch->status,
                'approved_by'            => $batch->approvedBy?->name,
                'approved_at'            => $batch->approved_at?->toISOString(),
                'notes'                  => $batch->notes,
            ],
            'allocations' => $allocations,
        ];
    }
}
