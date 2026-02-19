<?php

namespace App\Repositories\Eloquent;

use App\Models\Delivery;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryRepository extends BaseRepository implements DeliveryRepositoryInterface
{
    protected function model(): string
    {
        return Delivery::class;
    }

    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('status', $status)
            ->with(['customer', 'sale', 'assignedToUser'])
            ->orderBy('scheduled_date', 'desc')
            ->paginate($perPage);
    }

    public function getTodaySchedule(): Collection
    {
        return $this->newQuery()
            ->whereDate('scheduled_date', Carbon::today())
            ->whereIn('status', ['preparing', 'dispatched', 'in_transit'])
            ->with(['customer', 'sale', 'assignedToUser', 'deliveryItems.product'])
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }

    public function getByCustomer(int $customerId): Collection
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->with(['sale', 'assignedToUser'])
            ->orderBy('scheduled_date', 'desc')
            ->get();
    }

    public function getByDriver(int $userId): Collection
    {
        return $this->newQuery()
            ->where('assigned_to_user_id', $userId)
            ->with(['customer', 'sale', 'deliveryItems.product'])
            ->orderBy('scheduled_date', 'desc')
            ->get();
    }

    public function getByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->newQuery()
            ->whereBetween('scheduled_date', [$from, $to])
            ->with(['customer', 'sale', 'assignedToUser'])
            ->orderBy('scheduled_date', 'desc')
            ->get();
    }

    public function findByDeliveryNumber(string $deliveryNumber): ?Delivery
    {
        return $this->newQuery()
            ->where('delivery_number', $deliveryNumber)
            ->with(['customer', 'sale', 'assignedToUser', 'deliveryItems.product'])
            ->first();
    }

    public function getUpcoming(int $days): Collection
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays($days);

        return $this->newQuery()
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('status', ['preparing', 'dispatched'])
            ->with(['customer', 'sale', 'assignedToUser'])
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }

    public function getThisWeekByStatus(array $statuses, int $limit = 10): Collection
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return $this->newQuery()
            ->whereBetween('scheduled_date', [$startOfWeek, $endOfWeek])
            ->whereIn('status', $statuses)
            ->with(['sale:id,uuid,sale_number', 'customer:id,uuid,name'])
            ->orderBy('scheduled_date')
            ->limit($limit)
            ->get();
    }
}
