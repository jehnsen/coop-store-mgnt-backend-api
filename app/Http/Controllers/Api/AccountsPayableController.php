<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\MakeSupplierPaymentRequest;
use App\Http\Resources\PayableAgingResource;
use App\Http\Resources\PayableTransactionResource;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\PayableTransactionRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\PayableService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountsPayableController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PayableService $payableService,
        protected SupplierRepositoryInterface $supplierRepo,
        protected PayableTransactionRepositoryInterface $payableTransactionRepo,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepo
    ) {
    }

    /**
     * Get AP overview statistics.
     */
    public function overview(): JsonResponse
    {
        $stats = $this->supplierRepo->getAPOverviewStats();

        // Convert from centavos to pesos for display
        $totalOutstanding = $stats['total_outstanding'] / 100;
        $totalPurchases = $stats['total_purchases'] / 100;

        $response = [
            'total_suppliers' => $stats['total_suppliers'],
            'suppliers_with_balance' => $stats['suppliers_with_balance'],
            'total_outstanding' => $totalOutstanding,
            'total_purchases' => $totalPurchases,
            'average_payment_terms' => $stats['average_payment_terms'],
        ];

        return $this->successResponse($response, 'AP overview retrieved successfully');
    }

    /**
     * Get AP aging report.
     */
    public function aging(): JsonResponse
    {
        $store = auth()->user()->store;

        $report = $this->payableService->getAgingReport($store);

        return $this->successResponse([
            'suppliers' => PayableAgingResource::collection($report['suppliers']),
            'summary' => $report['summary'],
            'supplier_count' => $report['supplier_count'],
        ], 'AP aging report retrieved successfully');
    }

    /**
     * Get overdue payables.
     */
    public function overdue(): JsonResponse
    {
        $store = auth()->user()->store;

        $overdueAccounts = $this->payableService->getOverdueAccounts($store);

        return $this->successResponse([
            'accounts' => $overdueAccounts,
            'total_overdue' => $overdueAccounts->sum('overdue_amount'),
            'account_count' => $overdueAccounts->count(),
        ], 'Overdue payables retrieved successfully');
    }

    /**
     * Get supplier's payable transactions.
     */
    public function supplierPayables(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:invoice,payment,adjustment',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

        $perPage = $request->input('per_page', 15);

        $query = $this->payableTransactionRepo->newQuery()
            ->where('supplier_id', $supplier->id);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $transactions = $query
            ->with(['purchaseOrder', 'user'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);

        return $this->successResponse([
            'data' => PayableTransactionResource::collection($transactions->items()),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ], 'Payable transactions retrieved successfully');
    }

    /**
     * Get supplier's AP ledger (alias for supplierPayables).
     */
    public function supplierLedger(Request $request, string $uuid): JsonResponse
    {
        return $this->supplierPayables($request, $uuid);
    }

    /**
     * Make payment to supplier.
     */
    public function makePayment(MakeSupplierPaymentRequest $request, string $uuid): JsonResponse
    {
        try {
            $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

            $result = $this->payableService->makePayment(
                $supplier,
                $request->input('amount'),
                $request->input('payment_method'),
                $request->input('reference_number'),
                $request->input('invoice_ids'),
                $request->input('notes')
            );

            return $this->successResponse([
                'transaction' => new PayableTransactionResource($result['transaction']),
                'applied_to' => $result['applied_to'],
                'remaining_credit' => $result['remaining_credit'],
            ], 'Payment made successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to make payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get supplier statement for a date range.
     */
    public function statement(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

        $statement = $this->payableService->getSupplierStatement(
            $supplier,
            Carbon::parse($request->input('start_date')),
            Carbon::parse($request->input('end_date'))
        );

        return $this->successResponse($statement, 'Statement retrieved successfully');
    }

    /**
     * Get payment schedule (upcoming payments due).
     */
    public function paymentSchedule(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = $request->input('days', 30);
        $endDate = now()->addDays($days);

        $unpaidPOs = $this->purchaseOrderRepo->newQuery()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('payment_due_date', '<=', $endDate)
            ->where('payment_due_date', '>=', now())
            ->with('supplier')
            ->orderBy('payment_due_date', 'asc')
            ->get();

        $schedule = $unpaidPOs->map(function ($po) {
            $totalAmountCentavos = $po->getRawOriginal('total_amount');
            $amountPaidCentavos = $po->getRawOriginal('amount_paid') ?? 0;
            $outstandingCentavos = $totalAmountCentavos - $amountPaidCentavos;

            return [
                'supplier' => [
                    'uuid' => $po->supplier->uuid,
                    'code' => $po->supplier->code,
                    'name' => $po->supplier->name,
                ],
                'purchase_order' => [
                    'uuid' => $po->uuid,
                    'po_number' => $po->po_number,
                    'order_date' => $po->order_date?->toDateString(),
                ],
                'due_date' => $po->payment_due_date?->toDateString(),
                'days_until_due' => now()->diffInDays($po->payment_due_date, false),
                'amount_due' => $outstandingCentavos / 100,
                'payment_status' => $po->payment_status,
            ];
        });

        return $this->successResponse([
            'schedule' => $schedule,
            'total_due' => $schedule->sum('amount_due'),
            'count' => $schedule->count(),
        ], 'Payment schedule retrieved successfully');
    }

    /**
     * Get disbursement report (payments made to suppliers).
     */
    public function disbursementReport(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $from = Carbon::parse($request->input('start_date'));
        $to = Carbon::parse($request->input('end_date'));

        $report = $this->payableTransactionRepo->getDisbursementReport($from, $to);

        // Convert centavos to pesos for display
        $report['summary']['total_disbursed'] = $report['summary']['total_disbursed'] / 100;

        $report['by_method'] = collect($report['by_method'])->map(function ($item) {
            return [
                'payment_method' => $item->payment_method,
                'payment_count' => $item->payment_count,
                'total_disbursed' => $item->total_disbursed / 100,
            ];
        });

        $report['daily_disbursements'] = collect($report['daily_disbursements'])->map(function ($item) {
            return [
                'date' => $item->date,
                'payment_method' => $item->payment_method,
                'payment_count' => $item->payment_count,
                'total_disbursed' => $item->total_disbursed / 100,
            ];
        });

        return $this->successResponse($report, 'Disbursement report retrieved successfully');
    }
}
