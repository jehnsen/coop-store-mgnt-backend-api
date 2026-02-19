<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    use ApiResponse;

    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get daily sales report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function dailySales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $store = $request->user()->store;
        $date = Carbon::parse($request->date);

        $data = $this->reportService->dailySales($store, $date);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.daily-sales', compact('data', 'store'));
            return $pdf->download("daily-sales-{$date->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\DailySalesExport($data),
                "daily-sales-{$date->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get sales summary report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function salesSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        // Validate date range (max 1 year)
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        if ($startDate->diffInDays($endDate) > 365) {
            return $this->errorResponse('Date range cannot exceed 1 year', null, 422);
        }

        $store = $request->user()->store;
        $groupBy = $request->group_by ?? 'day';

        $data = $this->reportService->salesSummary($store, $startDate, $endDate, $groupBy);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-summary', compact('data', 'store'));
            return $pdf->download("sales-summary-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\SalesSummaryExport($data),
                "sales-summary-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get sales by category report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function salesByCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $store = $request->user()->store;

        $data = $this->reportService->salesByCategory($store, $startDate, $endDate);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-by-category', compact('data', 'store'));
            return $pdf->download("sales-by-category-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\SalesByCategoryExport($data),
                "sales-by-category-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get sales by customer report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function salesByCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $limit = $request->limit ?? 50;
        $store = $request->user()->store;

        $data = $this->reportService->salesByCustomer($store, $startDate, $endDate, $limit);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-by-customer', compact('data', 'store'));
            return $pdf->download("sales-by-customer-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\SalesByCustomerExport($data),
                "sales-by-customer-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get sales by payment method report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function salesByPaymentMethod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $store = $request->user()->store;

        $data = $this->reportService->salesByPaymentMethod($store, $startDate, $endDate);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-by-payment-method', compact('data', 'store'));
            return $pdf->download("sales-by-payment-method-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\SalesByPaymentMethodExport($data),
                "sales-by-payment-method-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get sales by cashier report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function salesByCashier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $store = $request->user()->store;

        $data = $this->reportService->salesByCashier($store, $startDate, $endDate);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.sales-by-cashier', compact('data', 'store'));
            return $pdf->download("sales-by-cashier-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\SalesByCashierExport($data),
                "sales-by-cashier-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get inventory valuation report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function inventoryValuation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $store = $request->user()->store;
        $data = $this->reportService->inventoryValuation($store);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.inventory-valuation', compact('data', 'store'));
            return $pdf->download("inventory-valuation-" . now()->format('Y-m-d') . ".pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\InventoryValuationExport($data),
                "inventory-valuation-" . now()->format('Y-m-d') . ".xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get stock movement report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function stockMovement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'product_id' => 'nullable|exists:products,id',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $productId = $request->product_id;
        $store = $request->user()->store;

        $data = $this->reportService->stockMovement($store, $startDate, $endDate, $productId);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.stock-movement', compact('data', 'store'));
            return $pdf->download("stock-movement-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\StockMovementExport($data),
                "stock-movement-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get low stock report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function lowStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $store = $request->user()->store;
        $data = $this->reportService->lowStockReport($store);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.low-stock', compact('data', 'store'));
            return $pdf->download("low-stock-" . now()->format('Y-m-d') . ".pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\LowStockExport($data),
                "low-stock-" . now()->format('Y-m-d') . ".xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get dead stock report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function deadStock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1|max:365',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $days = $request->days ?? 90;
        $store = $request->user()->store;
        $data = $this->reportService->deadStockReport($store, $days);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.dead-stock', compact('data', 'store'));
            return $pdf->download("dead-stock-{$days}-days-" . now()->format('Y-m-d') . ".pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\DeadStockExport($data),
                "dead-stock-{$days}-days-" . now()->format('Y-m-d') . ".xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get product profitability report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function productProfitability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:500',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $limit = $request->limit ?? 50;
        $store = $request->user()->store;

        $data = $this->reportService->productProfitability($store, $startDate, $endDate, $limit);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.product-profitability', compact('data', 'store'));
            return $pdf->download("product-profitability-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\ProductProfitabilityExport($data),
                "product-profitability-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get credit aging report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function creditAging(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $store = $request->user()->store;
        $data = $this->reportService->creditAgingReport($store);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.credit-aging', compact('data', 'store'));
            return $pdf->download("credit-aging-" . now()->format('Y-m-d') . ".pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\CreditAgingExport($data),
                "credit-aging-" . now()->format('Y-m-d') . ".xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get collection report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function collectionReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $store = $request->user()->store;

        $data = $this->reportService->collectionReport($store, $startDate, $endDate);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.collection-report', compact('data', 'store'));
            return $pdf->download("collection-report-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\CollectionReportExport($data),
                "collection-report-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get purchases by supplier report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function purchasesBySupplier(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $store = $request->user()->store;

        $data = $this->reportService->purchasesBySupplier($store, $startDate, $endDate);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.purchases-by-supplier', compact('data', 'store'));
            return $pdf->download("purchases-by-supplier-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\PurchasesBySupplierExport($data),
                "purchases-by-supplier-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.xlsx"
            );
        }

        return $this->successResponse($data);
    }

    /**
     * Get price comparison report.
     *
     * @param Request $request
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function priceComparison(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'export' => 'nullable|in:pdf,excel',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $productId = $request->product_id;
        $store = $request->user()->store;

        $data = $this->reportService->priceComparisonReport($store, $productId);

        // Handle export
        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('reports.price-comparison', compact('data', 'store'));
            return $pdf->download("price-comparison-" . now()->format('Y-m-d') . ".pdf");
        }

        if ($request->export === 'excel') {
            return Excel::download(
                new \App\Exports\PriceComparisonExport($data),
                "price-comparison-" . now()->format('Y-m-d') . ".xlsx"
            );
        }

        return $this->successResponse($data);
    }
}
