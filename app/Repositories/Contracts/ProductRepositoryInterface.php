<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Product;
use Carbon\Carbon;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Search products by name, SKU, or barcode
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find product by barcode
     */
    public function findByBarcode(string $barcode): ?Product;

    /**
     * Find product by SKU
     */
    public function findBySku(string $sku): ?Product;

    /**
     * Get products below reorder point
     */
    public function getLowStock(int $limit = 20): Collection;

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get active products only
     */
    public function getActive(): Collection;

    /**
     * Check if SKU exists (excluding specific product)
     */
    public function skuExists(string $sku, ?string $excludeUuid = null): bool;

    /**
     * Get products by multiple UUIDs
     */
    public function findManyByUuids(array $uuids): Collection;

    /**
     * Update stock quantity
     */
    public function updateStock(string $uuid, float $newStock): Product;

    /**
     * Get inventory valuation
     */
    public function getInventoryValuation(): array;

    /**
     * Get dead stock products (no sales in X days)
     */
    public function getDeadStock(int $days, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get inventory valuation by category
     */
    public function getInventoryValuationByCategory(): Collection;

    /**
     * Get low stock report with additional calculations
     */
    public function getLowStockReport(): Collection;

    /**
     * Get dead stock report (detailed version without pagination)
     */
    public function getDeadStockReport(int $days = 90): Collection;

    /**
     * Get product profitability analysis
     */
    public function getProductProfitability(Carbon $from, Carbon $to, int $limit = 50): Collection;
}
