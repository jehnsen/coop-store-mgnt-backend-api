<?php

namespace App\Repositories\Contracts;

use App\Models\Delivery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get by status
     */
    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get today's schedule
     */
    public function getTodaySchedule(): Collection;

    /**
     * Get by customer
     */
    public function getByCustomer(int $customerId): Collection;

    /**
     * Get by driver
     */
    public function getByDriver(int $userId): Collection;

    /**
     * Get by date range
     */
    public function getByDateRange(Carbon $from, Carbon $to): Collection;

    /**
     * Find by delivery number
     */
    public function findByDeliveryNumber(string $deliveryNumber): ?Delivery;

    /**
     * Get upcoming deliveries in next N days
     */
    public function getUpcoming(int $days): Collection;

    /**
     * Get deliveries for this week by status
     */
    public function getThisWeekByStatus(array $statuses, int $limit = 10): Collection;
}
