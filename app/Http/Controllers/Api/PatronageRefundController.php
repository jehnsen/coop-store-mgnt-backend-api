<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatronageRefund\ApprovePatronageRefundBatchRequest;
use App\Http\Requests\PatronageRefund\CreatePatronageRefundBatchRequest;
use App\Http\Requests\PatronageRefund\RecordPatronageDistributionRequest;
use App\Http\Resources\PatronageRefundAllocationResource;
use App\Http\Resources\PatronageRefundBatchResource;
use App\Models\PatronageRefundAllocation;
use App\Models\PatronageRefundBatch;
use App\Services\PatronageRefundService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatronageRefundController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PatronageRefundService $patronageRefundService,
    ) {
    }

    /**
     * GET /patronage-refunds
     * Paginated list of PR batches with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PatronageRefundBatch::where('store_id', Auth::user()->store_id)
            ->orderBy('period_from', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('q')) {
            $query->where('period_label', 'like', '%' . $request->input('q') . '%');
        }

        $batches = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $batches->setCollection(
                $batches->getCollection()->map(fn ($b) => new PatronageRefundBatchResource($b))
            ),
            'Patronage refund batches retrieved successfully.'
        );
    }

    /**
     * POST /patronage-refunds
     * Create a new PR batch (draft state, not yet computed).
     */
    public function store(CreatePatronageRefundBatchRequest $request): JsonResponse
    {
        $data             = $request->validated();
        $data['store_id'] = Auth::user()->store_id;

        $batch = PatronageRefundBatch::create($data);

        return $this->successResponse(
            new PatronageRefundBatchResource($batch),
            'Patronage refund batch created successfully.',
            201
        );
    }

    /**
     * GET /patronage-refunds/overview
     */
    public function overview(): JsonResponse
    {
        $overview = $this->patronageRefundService->getOverview(Auth::user()->store_id);
        return $this->successResponse($overview, 'Patronage refund overview retrieved successfully.');
    }

    /**
     * GET /patronage-refunds/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        return $this->successResponse(
            new PatronageRefundBatchResource($batch->load(['approvedBy:id,name'])),
            'Patronage refund batch retrieved successfully.'
        );
    }

    /**
     * GET /patronage-refunds/{uuid}/summary
     * Full batch summary with all allocations.
     */
    public function summary(string $uuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $summary = $this->patronageRefundService->getBatchSummary($batch);

        return $this->successResponse($summary, 'Patronage refund batch summary retrieved successfully.');
    }

    /**
     * POST /patronage-refunds/{uuid}/compute
     * (Re)compute allocations for a draft batch.
     */
    public function compute(string $uuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $result = $this->patronageRefundService->computeBatch($batch, Auth::user());

            return $this->successResponse($result, 'Patronage refund batch computed successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to compute batch.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /patronage-refunds/{uuid}/approve
     * Approve a computed draft batch.
     */
    public function approve(ApprovePatronageRefundBatchRequest $request, string $uuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $batch = $this->patronageRefundService->approveBatch($batch, $request->validated(), Auth::user());

            return $this->successResponse(
                new PatronageRefundBatchResource($batch->load('approvedBy:id,name')),
                'Patronage refund batch approved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve batch.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /patronage-refunds/{uuid}/allocations
     * Paginated list of allocations for a batch.
     */
    public function allocations(Request $request, string $uuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $query = PatronageRefundAllocation::where('batch_id', $batch->id)
            ->with('customer:id,uuid,name,member_id');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->whereHas('customer', fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('member_id', 'like', "%{$search}%")
            );
        }

        $allocs = $query->orderBy('allocation_amount', 'desc')
            ->paginate($request->input('per_page', 50));

        return $this->paginatedResponse(
            $allocs->setCollection(
                $allocs->getCollection()->map(fn ($a) => new PatronageRefundAllocationResource($a))
            ),
            'Allocations retrieved successfully.'
        );
    }

    /**
     * POST /patronage-refunds/{uuid}/allocations/{allocUuid}/pay
     * Record payment/distribution of a single allocation.
     */
    public function pay(RecordPatronageDistributionRequest $request, string $uuid, string $allocUuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $allocation = PatronageRefundAllocation::where('uuid', $allocUuid)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        try {
            $allocation = $this->patronageRefundService->recordDistribution(
                $allocation,
                $request->validated(),
                Auth::user()
            );

            return $this->successResponse(
                new PatronageRefundAllocationResource($allocation->load('customer:id,uuid,name,member_id')),
                'Distribution recorded successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record distribution.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /patronage-refunds/{uuid}/allocations/{allocUuid}/forfeit
     * Mark an allocation as forfeited.
     */
    public function forfeit(Request $request, string $uuid, string $allocUuid): JsonResponse
    {
        $batch = PatronageRefundBatch::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $allocation = PatronageRefundAllocation::where('uuid', $allocUuid)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $allocation = $this->patronageRefundService->forfeitAllocation(
                $allocation,
                $request->only('notes'),
                Auth::user()
            );

            return $this->successResponse(
                new PatronageRefundAllocationResource($allocation->load('customer:id,uuid,name,member_id')),
                'Allocation forfeited successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to forfeit allocation.', ['error' => $e->getMessage()], 500);
        }
    }
}
