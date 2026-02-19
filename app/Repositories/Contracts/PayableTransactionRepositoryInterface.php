<?php

namespace App\Repositories\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayableTransactionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get by supplier
     */
    public function getBySupplier(int $supplierId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get invoice transactions
     */
    public function getInvoices(?int $supplierId = null): Collection;

    /**
     * Get payment transactions
     */
    public function getPayments(?int $supplierId = null): Collection;

    /**
     * Get overdue invoices
     */
    public function getOverdue(): Collection;

    /**
     * Get aging report (4 buckets: current, 31-60, 61-90, over-90)
     */
    public function getAgingReport(): array;

    /**
     * Get payment allocation history for invoice
     */
    public function getAllocationHistory(int $purchaseOrderId): Collection;

    /**
     * Get unpaid invoices for supplier (FIFO order)
     */
    public function getUnpaidInvoices(int $supplierId): Collection;

    /**
     * Get total outstanding for supplier
     */
    public function getTotalOutstanding(int $supplierId): int;

    /**
     * Get disbursement report (payments made to suppliers)
     */
    public function getDisbursementReport(Carbon $from, Carbon $to): array;

    /**
     * Get transactions for supplier statement (date range)
     */
    public function getStatementTransactions(int $supplierId, Carbon $from, Carbon $to): Collection;

    /**
     * Get opening balance before a date
     */
    public function getOpeningBalance(int $supplierId, Carbon $before): int;

    /**
     * Update paid date for invoice transactions related to a purchase order
     */
    public function updatePaidDateForPurchaseOrder(int $purchaseOrderId, ?Carbon $paidDate): int;

    /**
     * Count unpaid invoice transactions past due date
     */
    public function getUnpaidInvoicesCount(): int;
}
