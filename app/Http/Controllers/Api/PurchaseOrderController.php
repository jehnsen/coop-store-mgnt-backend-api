<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ReceivePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Criteria\FilterByColumn;
use App\Repositories\Criteria\FilterByStatus;
use App\Repositories\Criteria\FilterBySupplierUuid;
use App\Repositories\Criteria\OrderBy;
use App\Repositories\Criteria\WithRelations;
use App\Services\PurchaseOrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    protected PurchaseOrderService $purchaseOrderService;
    protected PurchaseOrderRepositoryInterface $purchaseOrderRepo;

    public function __construct(
        PurchaseOrderService $purchaseOrderService,
        PurchaseOrderRepositoryInterface $purchaseOrderRepo
    ) {
        $this->purchaseOrderService = $purchaseOrderService;
        $this->purchaseOrderRepo = $purchaseOrderRepo;
    }

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): JsonResponse
    {
        // Apply eager loading
        $this->purchaseOrderRepo->pushCriteria(
            new WithRelations(['supplier', 'purchaseOrderItems.product'])
        );

        // Search by PO number
        if ($request->filled('search')) {
            $this->purchaseOrderRepo->pushCriteria(
                new FilterByColumn('po_number', "%{$request->input('search')}%", 'LIKE')
            );
        }

        // Filter by status
        if ($request->filled('status')) {
            $this->purchaseOrderRepo->pushCriteria(
                new FilterByStatus($request->input('status'))
            );
        }

        // Filter by supplier UUID
        if ($request->filled('supplier_id')) {
            $this->purchaseOrderRepo->pushCriteria(
                new FilterBySupplierUuid($request->input('supplier_id'))
            );
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $this->purchaseOrderRepo->pushCriteria(
                new FilterByColumn('order_date', $request->input('date_from'), '>=')
            );
        }

        if ($request->filled('date_to')) {
            $this->purchaseOrderRepo->pushCriteria(
                new FilterByColumn('order_date', $request->input('date_to'), '<=')
            );
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $this->purchaseOrderRepo->pushCriteria(new OrderBy($sortBy, $sortOrder));

        // Pagination
        $perPage = $request->input('per_page', 15);
        $purchaseOrders = $this->purchaseOrderRepo->paginate($perPage);

        return $this->paginatedResponse(
            $purchaseOrders->setCollection(
                $purchaseOrders->getCollection()->map(fn($po) => new PurchaseOrderResource($po))
            ),
            'Purchase orders retrieved successfully'
        );
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($request->validated());

            return $this->successResponse(
                new PurchaseOrderResource($purchaseOrder),
                'Purchase order created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Display the specified purchase order.
     */
    public function show(string $uuid): JsonResponse
    {
        $this->purchaseOrderRepo->pushCriteria(
            new WithRelations(['supplier', 'purchaseOrderItems.product.unit', 'user', 'branch'])
        );

        $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

        return $this->successResponse(
            new PurchaseOrderResource($purchaseOrder),
            'Purchase order retrieved successfully'
        );
    }

    /**
     * Update the specified purchase order.
     */
    public function update(UpdatePurchaseOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

            $updatedPurchaseOrder = $this->purchaseOrderService->updatePurchaseOrder(
                $purchaseOrder,
                $request->validated()
            );

            return $this->successResponse(
                new PurchaseOrderResource($updatedPurchaseOrder),
                'Purchase order updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

        // Only allow deletion of draft POs
        if ($purchaseOrder->status !== 'draft') {
            return $this->errorResponse(
                'Only draft purchase orders can be deleted. Current status: ' . $purchaseOrder->status,
                null,
                422
            );
        }

        $purchaseOrder->delete();

        return $this->successResponse(null, 'Purchase order deleted successfully');
    }

    /**
     * Submit a purchase order.
     */
    public function submit(string $uuid): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

            $submittedPurchaseOrder = $this->purchaseOrderService->submitPurchaseOrder($purchaseOrder);

            return $this->successResponse(
                new PurchaseOrderResource($submittedPurchaseOrder),
                'Purchase order submitted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Receive items from a purchase order.
     */
    public function receive(ReceivePurchaseOrderRequest $request, string $uuid): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

            $receivedPurchaseOrder = $this->purchaseOrderService->receivePurchaseOrder(
                $purchaseOrder,
                $request->input('items'),
                $request->input('notes')
            );

            return $this->successResponse(
                new PurchaseOrderResource($receivedPurchaseOrder),
                'Purchase order received successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

            $cancelledPurchaseOrder = $this->purchaseOrderService->cancelPurchaseOrder(
                $purchaseOrder,
                $request->input('reason')
            );

            return $this->successResponse(
                new PurchaseOrderResource($cancelledPurchaseOrder),
                'Purchase order cancelled successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Generate PDF for a purchase order.
     */
    public function pdf(string $uuid)
    {
        $this->purchaseOrderRepo->pushCriteria(
            new WithRelations(['supplier', 'purchaseOrderItems.product.unit', 'user', 'branch', 'store'])
        );

        $purchaseOrder = $this->purchaseOrderRepo->findByUuidOrFail($uuid);

        $pdf = Pdf::loadView('purchase-orders.po', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->download("PO-{$purchaseOrder->po_number}.pdf");
    }
}
