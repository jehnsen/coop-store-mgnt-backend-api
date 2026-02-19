<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Criteria\LowStockProducts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected function model(): string
    {
        return Product::class;
    }

    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with(['category', 'unit'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('sku', 'LIKE', "%{$query}%")
                    ->orWhere('barcode', 'LIKE', "%{$query}%");
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findByBarcode(string $barcode): ?Product
    {
        return $this->newQuery()
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->with(['category', 'unit'])
            ->first();
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->newQuery()
            ->where('sku', $sku)
            ->first();
    }

    public function getLowStock(int $limit = 20): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'reorder_point')
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->orderBy('current_stock', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->with(['unit'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getActive(): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function skuExists(string $sku, ?string $excludeUuid = null): bool
    {
        $query = $this->newQuery()->where('sku', $sku);

        if ($excludeUuid) {
            $query->where('uuid', '!=', $excludeUuid);
        }

        return $query->exists();
    }

    public function findManyByUuids(array $uuids): Collection
    {
        return $this->newQuery()
            ->whereIn('uuid', $uuids)
            ->where('is_active', true)
            ->get()
            ->keyBy('uuid');
    }

    public function updateStock(string $uuid, float $newStock): Product
    {
        $product = $this->findByUuidOrFail($uuid);
        $product->update(['current_stock' => $newStock]);
        return $product->fresh();
    }

    public function getInventoryValuation(): array
    {
        $storeId = auth()->user()->store_id;

        $result = $this->newQuery()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->selectRaw('
                COUNT(*) as total_products,
                SUM(current_stock) as total_units,
                SUM(current_stock * cost_price) as total_cost_value,
                SUM(current_stock * retail_price) as total_retail_value
            ')
            ->first();

        return [
            'total_products' => (int) ($result->total_products ?? 0),
            'total_units' => (float) ($result->total_units ?? 0),
            'total_cost_value' => (int) ($result->total_cost_value ?? 0),
            'total_retail_value' => (int) ($result->total_retail_value ?? 0),
            'potential_profit' => (int) (($result->total_retail_value ?? 0) - ($result->total_cost_value ?? 0)),
        ];
    }

    public function getDeadStock(int $days, int $perPage = 15): LengthAwarePaginator
    {
        $cutoffDate = Carbon::now()->subDays($days);

        return $this->newQuery()
            ->with(['category', 'unit'])
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->where('current_stock', '>', 0)
            ->whereDoesntHave('saleItems', function ($query) use ($cutoffDate) {
                $query->whereHas('sale', function ($q) use ($cutoffDate) {
                    $q->where('sale_date', '>=', $cutoffDate)
                      ->where('status', '!=', 'voided');
                });
            })
            ->orderBy('current_stock', 'desc')
            ->paginate($perPage);
    }

    public function getInventoryValuationByCategory(): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.id as category_id,
                categories.name as category_name,
                COUNT(products.id) as product_count,
                SUM(products.current_stock) as total_units,
                SUM(products.current_stock * products.cost_price) as total_value
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_value')
            ->get();
    }

    public function getLowStockReport(): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'reorder_point')
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->orderByRaw('(current_stock / NULLIF(reorder_point, 0)) ASC')
            ->get();
    }

    public function getDeadStockReport(int $days = 90): Collection
    {
        $cutoffDate = Carbon::now()->subDays($days);

        return $this->newQuery()
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->where('is_active', true)
            ->where('current_stock', '>', 0)
            ->whereDoesntHave('saleItems', function ($query) use ($cutoffDate) {
                $query->whereHas('sale', function ($q) use ($cutoffDate) {
                    $q->where('sale_date', '>=', $cutoffDate)
                      ->where('status', 'completed');
                });
            })
            ->get();
    }

    public function getProductProfitability(Carbon $from, Carbon $to, int $limit = 50): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.store_id', auth()->user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'products.id as product_id',
                'products.uuid as product_uuid',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.line_total) as total_revenue'),
                DB::raw('SUM(sale_items.quantity * products.cost_price) as total_cost')
            )
            ->groupBy('products.id', 'products.uuid', 'products.name', 'products.sku')
            ->orderByDesc(DB::raw('SUM(sale_items.line_total) - SUM(sale_items.quantity * products.cost_price)'))
            ->limit($limit)
            ->get();
    }
}
