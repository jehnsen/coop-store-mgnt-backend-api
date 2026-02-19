<?php

namespace App\Services;

use App\Models\Store;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService
{
    protected SaleRepositoryInterface $saleRepo;
    protected ProductRepositoryInterface $productRepo;
    protected CustomerRepositoryInterface $customerRepo;
    protected DeliveryRepositoryInterface $deliveryRepo;

    public function __construct(
        SaleRepositoryInterface $saleRepo,
        ProductRepositoryInterface $productRepo,
        CustomerRepositoryInterface $customerRepo,
        DeliveryRepositoryInterface $deliveryRepo
    ) {
        $this->saleRepo = $saleRepo;
        $this->productRepo = $productRepo;
        $this->customerRepo = $customerRepo;
        $this->deliveryRepo = $deliveryRepo;
    }
    /**
     * Get today's summary statistics.
     *
     * @param Store $store
     * @return array
     */
    public function getTodaySummary(Store $store): array
    {
        $today = Carbon::today();

        return Cache::remember("dashboard_summary_{$store->id}_" . $today->format('Y-m-d'), 300, function () use ($store, $today) {
            // Today's sales - use repository methods
            $transactionCount = $this->saleRepo->getTodayTransactionCount();
            $totalSales = $this->saleRepo->getTodayTotalSales();

            // Low stock products count
            $lowStockCount = $this->productRepo->getLowStock(999999)->count();

            // Outstanding credit
            $creditStats = $this->customerRepo->getCreditOverviewStats();
            $outstandingCredit = $creditStats['total_outstanding'];

            // Pending deliveries - using upcoming method to get pending deliveries
            $pendingDeliveries = $this->deliveryRepo->getUpcoming(365)
                ->whereIn('status', ['preparing', 'dispatched', 'in_transit'])
                ->count();

            return [
                'today_sales' => [
                    'amount' => (int) $totalSales,
                    'amount_pesos' => $totalSales / 100,
                    'transaction_count' => (int) $transactionCount,
                ],
                'low_stock_count' => $lowStockCount,
                'outstanding_credit' => [
                    'amount' => (int) $outstandingCredit,
                    'amount_pesos' => $outstandingCredit / 100,
                ],
                'pending_deliveries' => $pendingDeliveries,
            ];
        });
    }

    /**
     * Get sales trend data for the last N days.
     *
     * @param Store $store
     * @param int $days
     * @return array
     */
    public function getSalesTrend(Store $store, int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        return Cache::remember("sales_trend_{$store->id}_{$days}_" . Carbon::today()->format('Y-m-d'), 900, function () use ($startDate, $endDate) {
            $sales = $this->saleRepo->getSalesTrend($startDate, $endDate);

            return $sales->map(function ($sale) {
                return [
                    'date' => $sale->date,
                    'total' => (int) $sale->total,
                    'total_pesos' => $sale->total / 100,
                    'transactions' => (int) $sale->transactions,
                ];
            })->toArray();
        });
    }

    /**
     * Get top selling products.
     *
     * @param Store $store
     * @param int $limit
     * @param int $days
     * @return array
     */
    public function getTopProducts(Store $store, int $limit = 10, int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        return Cache::remember("top_products_{$store->id}_{$limit}_{$days}_" . Carbon::today()->format('Y-m-d'), 900, function () use ($startDate, $endDate, $limit) {
            return $this->saleRepo->getTopProducts($limit, $startDate, $endDate)
                ->map(function ($product) {
                    return [
                        'uuid' => $product->uuid,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'total_quantity' => (float) $product->total_quantity,
                        'total_revenue' => (int) $product->total_revenue,
                        'total_revenue_pesos' => $product->total_revenue / 100,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get sales by category for pie chart.
     *
     * @param Store $store
     * @param int $days
     * @return array
     */
    public function getSalesByCategory(Store $store, int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        return Cache::remember("sales_by_category_{$store->id}_{$days}_" . Carbon::today()->format('Y-m-d'), 900, function () use ($startDate, $endDate) {
            return $this->saleRepo->getSalesByCategory($startDate, $endDate)
                ->map(function ($category) {
                    return [
                        'name' => $category->category_name,
                        'slug' => $category->category_slug,
                        'total' => (int) $category->total_amount,
                        'total_pesos' => $category->total_amount / 100,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get recent transactions.
     *
     * @param Store $store
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentTransactions(Store $store, int $limit = 10)
    {
        return $this->saleRepo->getRecent($limit);
    }

    /**
     * Get stock alerts (products below reorder point).
     *
     * @param Store $store
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStockAlerts(Store $store)
    {
        return $this->productRepo->getLowStock(20);
    }

    /**
     * Get upcoming deliveries this week.
     *
     * @param Store $store
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUpcomingDeliveries(Store $store)
    {
        return $this->deliveryRepo->getThisWeekByStatus(['preparing', 'dispatched', 'in_transit'], 10);
    }

    /**
     * Get top customers by purchase amount.
     *
     * @param Store $store
     * @param int $limit
     * @param int $days
     * @return array
     */
    public function getTopCustomers(Store $store, int $limit = 5, int $days = 30): array
    {
        $startDate = Carbon::today()->subDays($days - 1);
        $endDate = Carbon::today();

        return Cache::remember("top_customers_{$store->id}_{$limit}_{$days}_" . Carbon::today()->format('Y-m-d'), 900, function () use ($startDate, $endDate, $limit) {
            return $this->customerRepo->getTopCustomers($limit, $startDate, $endDate)
                ->map(function ($customer) {
                    return [
                        'uuid' => $customer->uuid,
                        'name' => $customer->name,
                        'type' => $customer->type,
                        'transaction_count' => (int) $customer->transaction_count,
                        'total_purchases' => (int) $customer->total_purchases,
                        'total_purchases_pesos' => $customer->total_purchases / 100,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get comprehensive dashboard statistics.
     *
     * @param Store $store
     * @return array
     */
    public function getComprehensiveStats(Store $store): array
    {
        return [
            'summary' => $this->getTodaySummary($store),
            'sales_trend' => $this->getSalesTrend($store, 7), // Last 7 days for quick view
            'top_products' => $this->getTopProducts($store, 5),
            'sales_by_category' => $this->getSalesByCategory($store, 30),
            'recent_transactions' => $this->getRecentTransactions($store, 5),
            'stock_alerts_count' => $this->getStockAlerts($store)->count(),
            'upcoming_deliveries_count' => $this->getUpcomingDeliveries($store)->count(),
            'top_customers' => $this->getTopCustomers($store, 3),
        ];
    }
}
