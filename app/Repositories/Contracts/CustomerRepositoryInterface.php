<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Search customers by name, phone, email, code
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator;

    /**
     * Filter by customer type
     */
    public function filterByType(string $type): self;

    /**
     * Filter by outstanding balance
     */
    public function filterByOutstanding(bool $hasOutstanding): self;

    /**
     * Get with sales count
     */
    public function getWithSalesCount(): Collection;

    /**
     * Get credit overview statistics
     */
    public function getCreditOverviewStats(): array;

    /**
     * Find by customer code
     */
    public function getByCode(string $code): ?Customer;

    /**
     * Get top customers by purchases
     */
    public function getTopCustomers(int $limit, Carbon $from, Carbon $to): Collection;

    /**
     * Get customers with outstanding balance
     */
    public function getWithOutstanding(): Collection;

    /**
     * Get customers with overdue invoices
     */
    public function getWithOverdueInvoices(): Collection;
}
