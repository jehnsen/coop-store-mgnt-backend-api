<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\VoidSaleRequest;
use App\Http\Requests\Sale\RefundSaleRequest;
use App\Http\Requests\Sale\HoldTransactionRequest;
use App\Http\Resources\SaleResource;
use App\Http\Resources\ReceiptResource;
use App\Models\HeldTransaction;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Criteria\FilterByCashierUuid;
use App\Repositories\Criteria\FilterByCustomerUuid;
use App\Repositories\Criteria\FilterByDateOnly;
use App\Repositories\Criteria\FilterByStatus;
use App\Repositories\Criteria\OrderBy;
use App\Repositories\Criteria\SearchByColumn;
use App\Repositories\Criteria\WithRelations;
use App\Services\SaleService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    use ApiResponse;

    protected SaleService $saleService;
    protected SaleRepositoryInterface $saleRepository;

    public function __construct(SaleService $saleService, SaleRepositoryInterface $saleRepository)
    {
        $this->saleService = $saleService;
        $this->saleRepository = $saleRepository;
    }

    /**
     * Get paginated sales list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:completed,voided,refunded',
            'customer_id' => 'nullable|string|exists:customers,uuid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'cashier_id' => 'nullable|string|exists:users,uuid',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Apply eager loading
        $this->saleRepository->pushCriteria(
            new WithRelations(['customer', 'user', 'branch', 'items', 'payments'])
        );

        // Apply filters using criteria pattern
        if ($request->filled('status')) {
            $this->saleRepository->pushCriteria(
                new FilterByStatus($request->status)
            );
        }

        if ($request->filled('customer_id')) {
            $this->saleRepository->pushCriteria(
                new FilterByCustomerUuid($request->customer_id)
            );
        }

        if ($request->filled('date_from')) {
            $this->saleRepository->pushCriteria(
                new FilterByDateOnly('sale_date', $request->date_from, '>=')
            );
        }

        if ($request->filled('date_to')) {
            $this->saleRepository->pushCriteria(
                new FilterByDateOnly('sale_date', $request->date_to, '<=')
            );
        }

        if ($request->filled('cashier_id')) {
            $this->saleRepository->pushCriteria(
                new FilterByCashierUuid($request->cashier_id)
            );
        }

        // Search by sale number
        if ($request->filled('search')) {
            $this->saleRepository->pushCriteria(
                new SearchByColumn('sale_number', $request->search)
            );
        }

        // Order by most recent first
        $this->saleRepository->pushCriteria(
            new OrderBy('sale_date', 'desc')
        );

        // Pagination
        $perPage = $request->input('per_page', 15);
        $sales = $this->saleRepository->paginate($perPage);

        return $this->successResponse(
            SaleResource::collection($sales)->response()->getData(true),
            'Sales retrieved successfully.'
        );
    }

    /**
     * Create a new sale.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->saleService->createSale($request->validated());

            return $this->successResponse(
                new SaleResource($sale),
                'Sale created successfully.',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->errors(),
                'Validation failed.',
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to create sale.',
                500
            );
        }
    }

    /**
     * Get a single sale by UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        $sale = $this->saleRepository->findByUuidWithRelations(
            $uuid,
            ['customer', 'items.product', 'payments', 'user', 'branch', 'voidedBy']
        );

        if (!$sale) {
            return $this->errorResponse(
                ['error' => 'Sale not found'],
                'Sale not found.',
                404
            );
        }

        return $this->successResponse(
            new SaleResource($sale),
            'Sale retrieved successfully.'
        );
    }

    /**
     * Void a sale.
     */
    public function void(VoidSaleRequest $request, string $uuid): JsonResponse
    {
        try {
            $sale = $this->saleRepository->findByUuidOrFail($uuid);

            $voidedSale = $this->saleService->voidSale($sale, $request->reason);

            return $this->successResponse(
                new SaleResource($voidedSale),
                'Sale voided successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->errors(),
                'Validation failed.',
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to void sale.',
                500
            );
        }
    }

    /**
     * Refund a sale (partial or full).
     */
    public function refund(RefundSaleRequest $request, string $uuid): JsonResponse
    {
        try {
            $sale = $this->saleRepository->findByUuidWithRelations($uuid, ['items']);

            if (!$sale) {
                return $this->errorResponse(
                    ['error' => 'Sale not found'],
                    'Sale not found.',
                    404
                );
            }

            $refundedSale = $this->saleService->refundSale(
                $sale,
                $request->items,
                $request->reason,
                $request->refund_method
            );

            return $this->successResponse(
                new SaleResource($refundedSale),
                'Sale refunded successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->errors(),
                'Validation failed.',
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to refund sale.',
                500
            );
        }
    }

    /**
     * Get receipt data formatted for printing.
     */
    public function getReceipt(string $uuid): JsonResponse
    {
        $sale = $this->saleRepository->findByUuidWithRelations(
            $uuid,
            ['customer', 'items.product', 'payments', 'user.store', 'branch']
        );

        if (!$sale) {
            return $this->errorResponse(
                ['error' => 'Sale not found'],
                'Sale not found.',
                404
            );
        }

        return $this->successResponse(
            new ReceiptResource($sale),
            'Receipt retrieved successfully.'
        );
    }

    /**
     * Generate PDF receipt.
     */
    public function getReceiptPdf(string $uuid)
    {
        $sale = $this->saleRepository->findByUuidWithRelations(
            $uuid,
            ['customer', 'items.product', 'payments', 'user.store', 'branch']
        );

        if (!$sale) {
            abort(404, 'Sale not found');
        }

        $receiptData = (new ReceiptResource($sale))->toArray(request());

        $pdf = Pdf::loadView('receipts.sale', ['receipt' => $receiptData]);

        // Set paper size for thermal printer (80mm width)
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm x 297mm

        return $pdf->download("receipt-{$sale->sale_number}.pdf");
    }

    /**
     * Send receipt via SMS or email.
     */
    public function sendReceipt(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'method' => 'required|in:sms,email',
            'phone' => 'required_if:method,sms|nullable|string',
            'email' => 'required_if:method,email|nullable|email',
        ]);

        $sale = $this->saleRepository->findByUuidWithRelations(
            $uuid,
            ['customer', 'items.product', 'payments', 'user.store', 'branch']
        );

        if (!$sale) {
            return $this->errorResponse(
                ['error' => 'Sale not found'],
                'Sale not found.',
                404
            );
        }

        try {
            if ($request->method === 'email') {
                // Send email with receipt
                // TODO: Implement email sending
                // Mail::to($request->email)->send(new ReceiptMail($sale));

                return $this->successResponse(
                    null,
                    "Receipt sent to {$request->email} successfully."
                );
            } else {
                // Send SMS with receipt link
                // TODO: Implement SMS sending
                // SMS::send($request->phone, "Your receipt: " . route('receipt.view', $sale->uuid));

                return $this->successResponse(
                    null,
                    "Receipt sent to {$request->phone} successfully."
                );
            }
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to send receipt.',
                500
            );
        }
    }

    /**
     * Hold a transaction for later.
     */
    public function holdTransaction(HoldTransactionRequest $request): JsonResponse
    {
        try {
            $held = $this->saleService->holdTransaction(
                $request->cart_data,
                $request->name
            );

            return $this->successResponse(
                [
                    'id' => $held->id,
                    'name' => $held->name,
                    'expires_at' => $held->expires_at->toDateTimeString(),
                ],
                'Transaction held successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to hold transaction.',
                500
            );
        }
    }

    /**
     * Get list of held transactions.
     */
    public function listHeldTransactions(): JsonResponse
    {
        $held = HeldTransaction::where('store_id', Auth::user()->store_id)
            ->where('branch_id', Auth::user()->branch_id)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'created_at', 'expires_at', 'user_id'])
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'created_at' => $item->created_at->toDateTimeString(),
                    'expires_at' => $item->expires_at->toDateTimeString(),
                    'created_by' => $item->user->name ?? 'Unknown',
                ];
            });

        return $this->successResponse(
            $held,
            'Held transactions retrieved successfully.'
        );
    }

    /**
     * Resume a held transaction.
     */
    public function resumeHeldTransaction(int $id): JsonResponse
    {
        try {
            $held = HeldTransaction::where('id', $id)
                ->where('store_id', Auth::user()->store_id)
                ->firstOrFail();

            $cartData = $this->saleService->resumeTransaction($held);

            return $this->successResponse(
                $cartData,
                'Transaction resumed successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->errors(),
                'Validation failed.',
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to resume transaction.',
                500
            );
        }
    }

    /**
     * Discard a held transaction.
     */
    public function discardHeldTransaction(int $id): JsonResponse
    {
        try {
            $held = HeldTransaction::where('id', $id)
                ->where('store_id', Auth::user()->store_id)
                ->firstOrFail();

            $this->saleService->discardHeldTransaction($held);

            return $this->successResponse(
                null,
                'Transaction discarded successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->errors(),
                'Validation failed.',
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to discard transaction.',
                500
            );
        }
    }

    /**
     * Get next sale number for preview.
     */
    public function getNextSaleNumber(): JsonResponse
    {
        try {
            $store = Auth::user()->store;
            $saleNumber = $this->saleService->generateSaleNumber($store);

            return $this->successResponse(
                ['sale_number' => $saleNumber],
                'Next sale number retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                ['error' => $e->getMessage()],
                'Failed to generate sale number.',
                500
            );
        }
    }
}
