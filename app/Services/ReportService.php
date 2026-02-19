<?php

namespace App\Services;

use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        protected SaleRepositoryInterface $saleRepository,
        protected ProductRepositoryInterface $productRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        protected CreditTransactionRepositoryInterface $creditTransactionRepository,
        protected SupplierRepositoryInterface $supplierRepository,
        protected CreditService $creditService
    ) {
    }

    /**
     * Get daily sales report for specific date.
     *
     * @param Carbon $date
     * @return array
     */
    public function dailySales(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Get daily sales report (hourly breakdown + summary)
        $reportData = $this->saleRepository->getDailySalesReport($date);

        // Format hourly sales
        $hourlySales = $reportData['hourly_breakdown']->map(function ($row) {
            return [
                'hour' => $row->hour,
                'transaction_count' => $row->transaction_count,
                'total_sales' => $row->total_sales / 100,
            ];
        });

        // Payment methods breakdown
        $paymentMethods = $this->saleRepository->getPaymentMethodBreakdown($startOfDay, $endOfDay);
        $paymentMethodsFormatted = collect($paymentMethods)->map(function ($row) {
            return [
                'method' => $row->method,
                'count' => $row->count,
                'total' => $row->total / 100,
            ];
        });

        // Top products sold today
        $topProducts = $this->saleRepository->getTopProducts(10, $startOfDay, $endOfDay);
        $topProductsFormatted = $topProducts->map(function ($row) {
            return [
                'product_uuid' => $row->uuid,
                'product_name' => $row->name,
                'product_sku' => $row->sku,
                'quantity_sold' => $row->total_quantity,
                'revenue' => $row->total_revenue / 100,
            ];
        });

        // Format summary
        $summary = $reportData['summary'];

        return [
            'date' => $date->toDateString(),
            'summary' => [
                'transaction_count' => $summary->transaction_count ?? 0,
                'total_sales' => ($summary->total_sales ?? 0) / 100,
                'total_discounts' => ($summary->total_discounts ?? 0) / 100,
                'average_transaction' => ($summary->average_transaction ?? 0) / 100,
            ],
            'hourly_breakdown' => $hourlySales->toArray(),
            'payment_methods' => $paymentMethodsFormatted->toArray(),
            'top_products' => $topProductsFormatted->toArray(),
        ];
    }

    /**
     * Get sales summary for date range with grouping.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @param string $groupBy 'day', 'week', or 'month'
     * @return array
     */
    public function salesSummary(Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        $reportData = $this->saleRepository->getSalesSummaryGrouped($from, $to, $groupBy);

        // Format grouped sales data
        $salesData = $reportData['data']->map(function ($row) {
            return [
                'period' => $row->period,
                'transaction_count' => $row->transaction_count,
                'total_sales' => $row->total_sales / 100,
                'total_discounts' => $row->total_discounts / 100,
                'average_transaction' => $row->average_transaction / 100,
            ];
        });

        // Format overall summary
        $overallSummary = $reportData['summary'];

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'group_by' => $groupBy,
            ],
            'summary' => [
                'total_transactions' => $overallSummary->total_transactions ?? 0,
                'total_sales' => ($overallSummary->total_sales ?? 0) / 100,
                'total_discounts' => ($overallSummary->total_discounts ?? 0) / 100,
                'average_transaction' => ($overallSummary->average_transaction ?? 0) / 100,
            ],
            'data' => $salesData->toArray(),
        ];
    }

    /**
     * Get sales breakdown by category.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function salesByCategory(Carbon $from, Carbon $to): array
    {
        $categorySales = $this->saleRepository->getSalesByCategory($from, $to);

        // Calculate total for percentages
        $grandTotal = $categorySales->sum('total_amount');

        $data = $categorySales->map(function ($row) use ($grandTotal) {
            $totalSalesPesos = $row->total_amount / 100;
            return [
                'category_name' => $row->category_name,
                'total_quantity' => $row->total_quantity,
                'total_sales' => $totalSalesPesos,
                'transaction_count' => $row->sale_count,
                'percentage' => $grandTotal > 0 ? round(($row->total_amount / $grandTotal) * 100, 2) : 0,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'total_sales' => $grandTotal / 100,
            'data' => $data->toArray(),
        ];
    }

    /**
     * Get sales analysis by customer.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @param int $limit
     * @return array
     */
    public function salesByCustomer(Carbon $from, Carbon $to, int $limit = 50): array
    {
        $customerSales = $this->saleRepository->getSalesByCustomerReport($from, $to, $limit);

        $formattedData = $customerSales->map(function ($row) {
            return [
                'customer' => $row->customer ? [
                    'uuid' => $row->customer->uuid,
                    'code' => $row->customer->code,
                    'name' => $row->customer->name,
                    'email' => $row->customer->email,
                    'phone' => $row->customer->phone,
                ] : null,
                'transaction_count' => $row->transaction_count,
                'total_purchases' => $row->total_purchases / 100,
                'average_order_value' => $row->average_order_value / 100,
                'last_purchase_date' => $row->last_purchase_date,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get sales breakdown by payment method.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function salesByPaymentMethod(Carbon $from, Carbon $to): array
    {
        $paymentMethodSales = $this->saleRepository->getSalesByPaymentMethod($from, $to);

        $grandTotal = $paymentMethodSales->sum('total_amount');

        $data = $paymentMethodSales->map(function ($row) use ($grandTotal) {
            return [
                'method' => $row->method,
                'transaction_count' => $row->transaction_count,
                'total_amount' => $row->total_amount / 100,
                'percentage' => $grandTotal > 0 ? round(($row->total_amount / $grandTotal) * 100, 2) : 0,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'total_amount' => $grandTotal / 100,
            'data' => $data->toArray(),
        ];
    }

    /**
     * Get sales performance by cashier.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function salesByCashier(Carbon $from, Carbon $to): array
    {
        $cashierSales = $this->saleRepository->getSalesByCashier($from, $to);

        $formattedData = $cashierSales->map(function ($row) {
            return [
                'cashier_name' => $row->cashier_name,
                'transaction_count' => $row->transaction_count,
                'total_sales' => $row->total_sales / 100,
                'average_transaction' => ($row->total_sales / $row->transaction_count) / 100,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get inventory valuation report.
     *
     * @return array
     */
    public function inventoryValuation(): array
    {
        $categoryValuation = $this->productRepository->getInventoryValuationByCategory();

        $formattedData = $categoryValuation->map(function ($row) {
            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'product_count' => $row->product_count,
                'total_units' => $row->total_units,
                'total_value' => $row->total_value / 100,
            ];
        });

        $totalValue = $formattedData->sum('total_value');
        $totalUnits = $formattedData->sum('total_units');
        $totalProducts = $formattedData->sum('product_count');

        return [
            'summary' => [
                'total_products' => $totalProducts,
                'total_units' => $totalUnits,
                'total_value' => $totalValue,
            ],
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get stock movement history.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @param int|null $productId
     * @return array
     */
    public function stockMovement(Carbon $from, Carbon $to, ?int $productId = null): array
    {
        $query = StockAdjustment::where('store_id', auth()->user()->store_id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['product:id,name,sku', 'user:id,name', 'branch:id,name']);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'uuid' => $adjustment->uuid,
                    'date' => $adjustment->created_at->format('Y-m-d H:i:s'),
                    'product' => $adjustment->product ? [
                        'id' => $adjustment->product->id,
                        'name' => $adjustment->product->name,
                        'sku' => $adjustment->product->sku,
                    ] : null,
                    'branch' => $adjustment->branch ? [
                        'id' => $adjustment->branch->id,
                        'name' => $adjustment->branch->name,
                    ] : null,
                    'type' => $adjustment->type,
                    'quantity_before' => $adjustment->quantity_before,
                    'quantity_change' => $adjustment->quantity_change,
                    'quantity_after' => $adjustment->quantity_after,
                    'reason' => $adjustment->reason,
                    'notes' => $adjustment->notes,
                    'user' => $adjustment->user ? [
                        'id' => $adjustment->user->id,
                        'name' => $adjustment->user->name,
                    ] : null,
                ];
            });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'product_id' => $productId,
            'data' => $movements->toArray(),
        ];
    }

    /**
     * Get low stock alert report.
     *
     * @return array
     */
    public function lowStockReport(): array
    {
        $lowStockProducts = $this->productRepository->getLowStockReport();

        $formattedData = $lowStockProducts->map(function ($product) {
            // Estimate days until stockout based on recent sales
            $avgDailySales = SaleItem::where('product_id', $product->id)
                ->whereHas('sale', function ($query) {
                    $query->where('status', 'completed')
                        ->where('sale_date', '>=', now()->subDays(30));
                })
                ->avg('quantity') ?? 0;

            $daysUntilStockout = $avgDailySales > 0
                ? round($product->current_stock / $avgDailySales, 1)
                : null;

            return [
                'uuid' => $product->uuid,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category?->name,
                'current_stock' => $product->current_stock,
                'reorder_point' => $product->reorder_point,
                'minimum_order_qty' => $product->minimum_order_qty,
                'unit' => $product->unit?->abbreviation,
                'stock_percentage' => $product->reorder_point > 0
                    ? round(($product->current_stock / $product->reorder_point) * 100, 2)
                    : 0,
                'estimated_days_until_stockout' => $daysUntilStockout,
                'urgency' => $product->current_stock <= 0 ? 'critical'
                    : ($product->current_stock <= ($product->reorder_point * 0.5) ? 'high' : 'medium'),
            ];
        });

        return [
            'count' => $formattedData->count(),
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get dead stock report (no sales in X days).
     *
     * @param int $days
     * @return array
     */
    public function deadStockReport(int $days = 90): array
    {
        $cutoffDate = now()->subDays($days);

        $deadStockProducts = $this->productRepository->getDeadStockReport($days);

        $formattedData = $deadStockProducts->map(function ($product) use ($cutoffDate) {
            // Get last sale date
            $lastSale = SaleItem::where('product_id', $product->id)
                ->whereHas('sale', function ($query) {
                    $query->where('status', 'completed');
                })
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->orderBy('sales.sale_date', 'desc')
                ->first();

            $daysSinceLastSale = $lastSale
                ? now()->diffInDays($lastSale->sale_date)
                : null;

            $stockValue = $product->current_stock * ($product->getRawOriginal('cost_price') / 100);

            return [
                'uuid' => $product->uuid,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category?->name,
                'current_stock' => $product->current_stock,
                'cost_price' => $product->cost_price,
                'stock_value' => $stockValue,
                'days_since_last_sale' => $daysSinceLastSale,
                'last_sale_date' => $lastSale?->sale_date,
            ];
        })->sortByDesc('stock_value')->values();

        return [
            'period_days' => $days,
            'count' => $formattedData->count(),
            'total_stock_value' => $formattedData->sum('stock_value'),
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get product profitability analysis.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @param int $limit
     * @return array
     */
    public function productProfitability(Carbon $from, Carbon $to, int $limit = 50): array
    {
        $profitability = $this->productRepository->getProductProfitability($from, $to, $limit);

        $formattedData = $profitability->map(function ($row) {
            $revenue = $row->total_revenue / 100;
            $cost = $row->total_cost / 100;
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

            return [
                'product_uuid' => $row->product_uuid,
                'product_sku' => $row->product_sku,
                'product_name' => $row->product_name,
                'quantity_sold' => $row->total_quantity,
                'total_revenue' => $revenue,
                'total_cost' => $cost,
                'gross_profit' => $profit,
                'margin_percentage' => $margin,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'summary' => [
                'total_revenue' => $formattedData->sum('total_revenue'),
                'total_cost' => $formattedData->sum('total_cost'),
                'total_profit' => $formattedData->sum('gross_profit'),
            ],
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get credit aging report.
     *
     * @return array
     */
    public function creditAgingReport(): array
    {
        return $this->creditService->getAgingReport(auth()->user()->store);
    }

    /**
     * Get collection report (credit payments received).
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function collectionReport(Carbon $from, Carbon $to): array
    {
        $reportData = $this->creditTransactionRepository->getCollectionReport($from, $to);

        // Format daily collections
        $dailyCollections = $reportData['daily_collections']->map(function ($row) {
            return [
                'date' => $row->date,
                'payment_method' => $row->payment_method,
                'payment_count' => $row->payment_count,
                'total_collected' => $row->total_collected / 100,
            ];
        });

        // Format method summary
        $methodSummary = $reportData['by_method']->map(function ($row) {
            return [
                'payment_method' => $row->payment_method,
                'payment_count' => $row->payment_count,
                'total_collected' => $row->total_collected / 100,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'summary' => [
                'total_collected' => $reportData['summary']['total_collected'] / 100,
                'total_payments' => $reportData['summary']['total_payments'],
            ],
            'by_method' => $methodSummary->toArray(),
            'daily_collections' => $dailyCollections->toArray(),
        ];
    }

    /**
     * Get purchases by supplier report.
     *
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    public function purchasesBySupplier(Carbon $from, Carbon $to): array
    {
        $supplierPurchases = $this->purchaseOrderRepository->getPurchasesBySupplier($from, $to);

        $formattedData = $supplierPurchases->map(function ($row) {
            return [
                'supplier_name' => $row->company_name,
                'po_count' => $row->po_count,
                'total_amount' => $row->total_amount / 100,
            ];
        });

        return [
            'period' => [
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
            ],
            'summary' => [
                'total_amount' => $formattedData->sum('total_amount'),
                'total_pos' => $formattedData->sum('po_count'),
            ],
            'data' => $formattedData->toArray(),
        ];
    }

    /**
     * Get price comparison report across suppliers.
     *
     * @param int|null $productId
     * @return array
     */
    public function priceComparisonReport(?int $productId = null): array
    {
        $supplierProducts = $this->supplierRepository->getPriceComparisonReport($productId);

        // Group by product
        $groupedData = $supplierProducts->groupBy('product_id')->map(function ($group) {
            $product = $group->first();

            $suppliers = $group->map(function ($sp) {
                return [
                    'supplier_code' => $sp->supplier_code,
                    'supplier_name' => $sp->supplier_name,
                    'supplier_sku' => $sp->supplier_sku,
                    'supplier_price' => $sp->supplier_price / 100,
                    'lead_time_days' => $sp->lead_time_days,
                    'minimum_order_qty' => $sp->minimum_order_quantity,
                    'is_preferred' => $sp->is_preferred,
                ];
            })->toArray();

            // Find lowest and highest prices
            $prices = collect($suppliers)->pluck('supplier_price');
            $lowestPrice = $prices->min();
            $highestPrice = $prices->max();

            return [
                'product' => [
                    'uuid' => $product->product_uuid,
                    'sku' => $product->product_sku,
                    'name' => $product->product_name,
                    'current_cost_price' => $product->current_cost_price / 100,
                ],
                'supplier_count' => count($suppliers),
                'lowest_price' => $lowestPrice,
                'highest_price' => $highestPrice,
                'price_variance' => $highestPrice - $lowestPrice,
                'suppliers' => $suppliers,
            ];
        })->values();

        return [
            'product_id' => $productId,
            'data' => $groupedData->toArray(),
        ];
    }
}
