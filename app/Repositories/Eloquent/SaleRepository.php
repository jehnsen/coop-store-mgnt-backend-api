<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    protected function model(): string
    {
        return Sale::class;
    }

    public function getTodaySales(): Collection
    {
        return $this->newQuery()
            ->whereDate('sale_date', Carbon::today())
            ->where('status', '!=', 'voided')
            ->with(['items.product', 'customer', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByDateRange(Carbon $from, Carbon $to, ?string $status = null): Collection
    {
        $query = $this->newQuery()
            ->whereBetween('sale_date', [$from, $to]);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with(['items.product', 'customer', 'user'])->get();
    }

    public function getByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->with(['items.product', 'user'])
            ->orderBy('sale_date', 'desc')
            ->paginate($perPage);
    }

    public function getRecent(int $limit = 10): Collection
    {
        return $this->newQuery()
            ->with(['customer:id,uuid,name', 'user:id,name', 'branch:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function findBySaleNumber(string $saleNumber): ?Sale
    {
        return $this->newQuery()
            ->where('sale_number', $saleNumber)
            ->with(['items.product', 'payments', 'customer'])
            ->first();
    }

    public function getSalesSummary(Carbon $from, Carbon $to): array
    {
        $summary = $this->newQuery()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_transaction
            ')
            ->first();

        return [
            'transaction_count' => (int) ($summary->transaction_count ?? 0),
            'total_sales' => (int) ($summary->total_sales ?? 0),
            'total_discounts' => (int) ($summary->total_discounts ?? 0),
            'average_transaction' => (int) ($summary->average_transaction ?? 0),
        ];
    }

    public function getNextSaleNumber(): string
    {
        $storeId = Auth::user()->store_id;
        $year = now()->year;
        $prefix = "INV-{$year}-";

        $lastSale = $this->newQuery()
            ->where('sale_number', 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->orderBy('sale_number', 'desc')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->sale_number, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function getTopProducts(int $limit, Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'products.uuid',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_revenue')
            )
            ->groupBy('products.id', 'products.uuid', 'products.name', 'products.sku')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSalesByCategory(Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'categories.name as category_name',
                'categories.slug as category_slug',
                DB::raw('COUNT(DISTINCT sales.id) as sale_count'),
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_amount')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    public function getSalesByPaymentMethod(Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'sale_payments.method',
                DB::raw('COUNT(DISTINCT sales.id) as transaction_count'),
                DB::raw('SUM(sale_payments.amount) as total_amount')
            )
            ->groupBy('sale_payments.method')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    public function getSalesByCashier(Carbon $from, Carbon $to): Collection
    {
        return DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->where('sales.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'users.name as cashier_name',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(sales.total_amount) as total_sales')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_sales', 'desc')
            ->get();
    }

    public function getTodayTransactionCount(): int
    {
        return $this->newQuery()
            ->whereDate('sale_date', Carbon::today())
            ->where('status', '!=', 'voided')
            ->count();
    }

    public function getTodayTotalSales(): int
    {
        return (int) $this->newQuery()
            ->whereDate('sale_date', Carbon::today())
            ->where('status', '!=', 'voided')
            ->sum('total_amount');
    }

    public function findByUuidWithRelations(string $uuid, array $relations = []): ?Sale
    {
        $query = $this->newQuery()->where('uuid', $uuid);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    public function getSalesTrend(Carbon $from, Carbon $to): Collection
    {
        return $this->newQuery()
            ->whereBetween('sale_date', [$from, $to])
            ->where('status', '!=', 'voided')
            ->selectRaw('DATE(sale_date) as date, SUM(total_amount) as total, COUNT(*) as transactions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function getUnpaidByCustomer(int $customerId, ?array $uuids = null): Collection
    {
        $query = $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('payment_status', '!=', 'paid');

        if ($uuids && count($uuids) > 0) {
            $query->whereIn('uuid', $uuids);
        }

        return $query->orderBy('sale_date', 'asc')->get();
    }

    public function getDailySalesReport(Carbon $date): array
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Hourly breakdown
        $hourlySales = $this->newQuery()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$startOfDay, $endOfDay])
            ->selectRaw('
                HOUR(sale_date) as hour,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Overall summary
        $summary = $this->newQuery()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$startOfDay, $endOfDay])
            ->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_transaction
            ')
            ->first();

        return [
            'hourly_breakdown' => $hourlySales,
            'summary' => $summary,
        ];
    }

    public function getSalesSummaryGrouped(Carbon $from, Carbon $to, string $groupBy = 'day'): array
    {
        // Determine date format for grouping
        $dateFormat = match ($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        // Get grouped sales data
        $salesData = $this->newQuery()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw("
                DATE_FORMAT(sale_date, '{$dateFormat}') as period,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_transaction
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Overall summary
        $overallSummary = $this->newQuery()
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_transaction
            ')
            ->first();

        return [
            'data' => $salesData,
            'summary' => $overallSummary,
        ];
    }

    public function getSalesByCustomerReport(Carbon $from, Carbon $to, int $limit = 50): Collection
    {
        return $this->newQuery()
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('
                customer_id,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_purchases,
                AVG(total_amount) as average_order_value,
                MAX(sale_date) as last_purchase_date
            ')
            ->with('customer:id,uuid,code,name,email,phone')
            ->groupBy('customer_id')
            ->orderByDesc('total_purchases')
            ->limit($limit)
            ->get();
    }

    public function getPaymentMethodBreakdown(Carbon $from, Carbon $to): array
    {
        $startOfDay = $from->copy()->startOfDay();
        $endOfDay = $to->copy()->endOfDay();

        return DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$startOfDay, $endOfDay])
            ->select(
                'sale_payments.method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(sale_payments.amount) as total')
            )
            ->groupBy('sale_payments.method')
            ->get()
            ->toArray();
    }
}
