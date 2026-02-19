<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected function model(): string
    {
        return Customer::class;
    }

    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->orWhere('mobile', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%")
                    ->orWhere('code', 'LIKE', "%{$query}%");
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function filterByType(string $type): self
    {
        $this->model = $this->newQuery()->where('type', $type)->getModel();
        return $this;
    }

    public function filterByOutstanding(bool $hasOutstanding): self
    {
        if ($hasOutstanding) {
            $this->model = $this->newQuery()->where('total_outstanding', '>', 0)->getModel();
        } else {
            $this->model = $this->newQuery()->where('total_outstanding', '=', 0)->getModel();
        }
        return $this;
    }

    public function getWithSalesCount(): Collection
    {
        return $this->newQuery()
            ->withCount('sales')
            ->orderBy('name')
            ->get();
    }

    public function getCreditOverviewStats(): array
    {
        $storeId = Auth::user()->store_id;

        $stats = $this->newQuery()
            ->selectRaw('
                COUNT(*) as total_customers,
                COUNT(CASE WHEN total_outstanding > 0 THEN 1 END) as customers_with_balance,
                SUM(CAST(total_outstanding AS SIGNED)) as total_outstanding,
                SUM(CAST(credit_limit AS SIGNED)) as total_credit_limit
            ')
            ->first();

        $totalOutstanding = (int) ($stats->total_outstanding ?? 0);
        $totalCreditLimit = (int) ($stats->total_credit_limit ?? 0);

        return [
            'total_customers' => (int) ($stats->total_customers ?? 0),
            'customers_with_balance' => (int) ($stats->customers_with_balance ?? 0),
            'total_outstanding' => $totalOutstanding,
            'total_credit_limit' => $totalCreditLimit,
            'available_credit' => $totalCreditLimit - $totalOutstanding,
        ];
    }

    public function getByCode(string $code): ?Customer
    {
        return $this->newQuery()
            ->where('code', $code)
            ->first();
    }

    public function getTopCustomers(int $limit, Carbon $from, Carbon $to): Collection
    {
        return DB::table('customers')
            ->join('sales', 'customers.id', '=', 'sales.customer_id')
            ->where('customers.store_id', Auth::user()->store_id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select(
                'customers.uuid',
                'customers.name',
                'customers.code',
                'customers.type',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total_amount) as total_purchases')
            )
            ->groupBy('customers.id', 'customers.uuid', 'customers.name', 'customers.code', 'customers.type')
            ->orderBy('total_purchases', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getWithOutstanding(): Collection
    {
        return $this->newQuery()
            ->where('total_outstanding', '>', 0)
            ->orderBy('total_outstanding', 'desc')
            ->get();
    }

    public function getWithOverdueInvoices(): Collection
    {
        return $this->newQuery()
            ->whereHas('creditTransactions', function ($query) {
                $query->where('type', 'charge')
                    ->whereNull('paid_date')
                    ->where('due_date', '<', Carbon::now());
            })
            ->with(['creditTransactions' => function ($query) {
                $query->where('type', 'charge')
                    ->whereNull('paid_date')
                    ->where('due_date', '<', Carbon::now());
            }])
            ->get();
    }
}
