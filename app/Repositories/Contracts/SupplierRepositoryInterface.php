<?php

namespace App\Repositories\Contracts;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Search suppliers by name, contact, phone
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get with purchase order count
     */
    public function getWithPurchaseOrderCount(): Collection;

    /**
     * Get active suppliers
     */
    public function getActive(): Collection;

    /**
     * Find by supplier code
     */
    public function findByCode(string $code): ?Supplier;

    /**
     * Get supplier's linked products
     */
    public function getSupplierProducts(string $supplierUuid): Collection;

    /**
     * Link product to supplier
     */
    public function linkProduct(string $supplierUuid, string $productUuid, array $data): bool;

    /**
     * Unlink product from supplier
     */
    public function unlinkProduct(string $supplierUuid, string $productUuid): bool;

    /**
     * Get price history for a product from this supplier
     */
    public function getPriceHistory(string $supplierUuid, ?string $productUuid = null, ?string $fromDate = null, ?string $toDate = null): Collection;

    /**
     * Get supplier statistics (total purchases, last purchase date)
     */
    public function getSupplierStatistics(string $supplierUuid): array;

    /**
     * Get price comparison report across suppliers for products
     */
    public function getPriceComparisonReport(?int $productId = null): Collection;

    /**
     * Get suppliers with outstanding balances (AP)
     */
    public function getWithOutstanding(): Collection;

    /**
     * Get suppliers with overdue invoices (AP)
     */
    public function getWithOverdueInvoices(): Collection;

    /**
     * Get AP overview statistics
     */
    public function getAPOverviewStats(): array;
}
