<?php

namespace App\Repositories\Contracts;

use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get by status
     */
    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get by supplier
     */
    public function getBySupplier(int $supplierId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get by date range
     */
    public function getByDateRange(Carbon $from, Carbon $to): Collection;

    /**
     * Find by PO number
     */
    public function findByPoNumber(string $poNumber): ?PurchaseOrder;

    /**
     * Get next PO number (with locking)
     */
    public function getNextPoNumber(): string;

    /**
     * Get pending receiving POs
     */
    public function getPendingReceiving(): Collection;

    /**
     * Get purchases by supplier (report)
     */
    public function getPurchasesBySupplier(Carbon $from, Carbon $to): Collection;

    /**
     * Get unpaid POs by supplier (FIFO order for payment allocation)
     */
    public function getUnpaidBySupplier(int $supplierId, ?array $poUuids = null): Collection;
}
