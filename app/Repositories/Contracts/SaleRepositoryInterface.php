<?php

namespace App\Repositories\Contracts;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SaleRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get sales for today
     */
    public function getTodaySales(): Collection;

    /**
     * Get sales by date range
     */
    public function getByDateRange(Carbon $from, Carbon $to, ?string $status = null): Collection;

    /**
     * Get sales by customer
     */
    public function getByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get recent sales
     */
    public function getRecent(int $limit = 10): Collection;

    /**
     * Find by sale number
     */
    public function findBySaleNumber(string $saleNumber): ?Sale;

    /**
     * Get sales summary for date range
     */
    public function getSalesSummary(Carbon $from, Carbon $to): array;

    /**
     * Get next sale number (with locking)
     */
    public function getNextSaleNumber(): string;

    /**
     * Get top selling products
     */
    public function getTopProducts(int $limit, Carbon $from, Carbon $to): Collection;

    /**
     * Get sales by category
     */
    public function getSalesByCategory(Carbon $from, Carbon $to): Collection;

    /**
     * Get sales by payment method
     */
    public function getSalesByPaymentMethod(Carbon $from, Carbon $to): Collection;

    /**
     * Get sales by cashier
     */
    public function getSalesByCashier(Carbon $from, Carbon $to): Collection;

    /**
     * Get today's transaction count
     */
    public function getTodayTransactionCount(): int;

    /**
     * Get today's total sales amount
     */
    public function getTodayTotalSales(): int;

    /**
     * Find sale by UUID with relationships
     */
    public function findByUuidWithRelations(string $uuid, array $relations = []): ?Sale;

    /**
     * Get sales trend grouped by date
     */
    public function getSalesTrend(Carbon $from, Carbon $to): Collection;

    /**
     * Get unpaid sales for customer (ordered by date for FIFO)
     * If uuids provided, filter by those specific sales
     */
    public function getUnpaidByCustomer(int $customerId, ?array $uuids = null): Collection;

    /**
     * Get daily sales report with hourly breakdown
     */
    public function getDailySalesReport(Carbon $date): array;

    /**
     * Get sales summary grouped by period (day, week, month)
     */
    public function getSalesSummaryGrouped(Carbon $from, Carbon $to, string $groupBy = 'day'): array;

    /**
     * Get sales by customer report
     */
    public function getSalesByCustomerReport(Carbon $from, Carbon $to, int $limit = 50): Collection;

    /**
     * Get payment method breakdown for date range
     */
    public function getPaymentMethodBreakdown(Carbon $from, Carbon $to): array;
}
