<?php

namespace App\Repositories\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CreditTransactionRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get by customer
     */
    public function getByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get charge transactions
     */
    public function getCharges(?int $customerId = null): Collection;

    /**
     * Get payment transactions
     */
    public function getPayments(?int $customerId = null): Collection;

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
    public function getAllocationHistory(int $saleId): Collection;

    /**
     * Get unpaid invoices for customer (FIFO order)
     */
    public function getUnpaidInvoices(int $customerId): Collection;

    /**
     * Get total outstanding for customer
     */
    public function getTotalOutstanding(int $customerId): int;

    /**
     * Get collection report (payments received)
     */
    public function getCollectionReport(Carbon $from, Carbon $to): array;

    /**
     * Get transactions for customer statement (date range)
     */
    public function getStatementTransactions(int $customerId, Carbon $from, Carbon $to): Collection;

    /**
     * Get opening balance before a date
     */
    public function getOpeningBalance(int $customerId, Carbon $before): int;

    /**
     * Update paid date for charge transactions related to a sale
     */
    public function updatePaidDateForSale(int $saleId, ?Carbon $paidDate): int;

    /**
     * Count unpaid charge transactions past due date
     */
    public function getUnpaidChargesCount(): int;
}
