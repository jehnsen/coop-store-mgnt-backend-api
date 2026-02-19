<?php

namespace App\Repositories\Eloquent;

use App\Models\CreditTransaction;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CreditTransactionRepository extends BaseRepository implements CreditTransactionRepositoryInterface
{
    protected function model(): string
    {
        return CreditTransaction::class;
    }

    public function getByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->with(['sale', 'user'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);
    }

    public function getCharges(?int $customerId = null): Collection
    {
        $query = $this->newQuery()->where('type', 'charge');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->with(['customer', 'sale'])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    public function getPayments(?int $customerId = null): Collection
    {
        $query = $this->newQuery()->where('type', 'payment');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        return $query->with(['customer'])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    public function getOverdue(): Collection
    {
        return $this->newQuery()
            ->where('type', 'charge')
            ->whereNull('paid_date')
            ->where('due_date', '<', Carbon::now())
            ->with(['customer', 'sale'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getAgingReport(): array
    {
        $storeId = Auth::user()->store_id;
        $today = Carbon::now();

        $transactions = $this->newQuery()
            ->where('type', 'charge')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->with('customer:id,uuid,name,code')
            ->get();

        $aging = [
            'current' => ['amount' => 0, 'count' => 0, 'customers' => []],
            '31-60' => ['amount' => 0, 'count' => 0, 'customers' => []],
            '61-90' => ['amount' => 0, 'count' => 0, 'customers' => []],
            'over-90' => ['amount' => 0, 'count' => 0, 'customers' => []],
        ];

        foreach ($transactions as $transaction) {
            $daysOverdue = $today->diffInDays(Carbon::parse($transaction->due_date), false);
            $daysOverdue = abs($daysOverdue);

            if ($daysOverdue <= 30) {
                $bucket = 'current';
            } elseif ($daysOverdue <= 60) {
                $bucket = '31-60';
            } elseif ($daysOverdue <= 90) {
                $bucket = '61-90';
            } else {
                $bucket = 'over-90';
            }

            $aging[$bucket]['amount'] += $transaction->amount;
            $aging[$bucket]['count']++;

            if (!in_array($transaction->customer->uuid, array_column($aging[$bucket]['customers'], 'uuid'))) {
                $aging[$bucket]['customers'][] = [
                    'uuid' => $transaction->customer->uuid,
                    'name' => $transaction->customer->name,
                    'code' => $transaction->customer->code,
                ];
            }
        }

        return $aging;
    }

    public function getAllocationHistory(int $saleId): Collection
    {
        return $this->newQuery()
            ->where('sale_id', $saleId)
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    public function getUnpaidInvoices(int $customerId): Collection
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('type', 'charge')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getTotalOutstanding(int $customerId): int
    {
        return (int) $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('type', 'charge')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->sum('amount');
    }

    public function getCollectionReport(Carbon $from, Carbon $to): array
    {
        // Daily collections
        $dailyCollections = $this->newQuery()
            ->where('type', 'payment')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                DATE(transaction_date) as date,
                payment_method,
                COUNT(*) as payment_count,
                SUM(ABS(amount)) as total_collected
            ')
            ->groupBy('date', 'payment_method')
            ->orderBy('date', 'desc')
            ->get();

        // Summary by payment method
        $methodSummary = $this->newQuery()
            ->where('type', 'payment')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                payment_method,
                COUNT(*) as payment_count,
                SUM(ABS(amount)) as total_collected
            ')
            ->groupBy('payment_method')
            ->get();

        // Overall summary
        $summary = $this->newQuery()
            ->where('type', 'payment')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as payment_count,
                SUM(ABS(amount)) as total_collected
            ')
            ->first();

        return [
            'summary' => [
                'total_collected' => (int) ($summary->total_collected ?? 0),
                'total_payments' => (int) ($summary->payment_count ?? 0),
            ],
            'by_method' => $methodSummary,
            'daily_collections' => $dailyCollections,
        ];
    }

    public function getStatementTransactions(int $customerId, Carbon $from, Carbon $to): Collection
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->whereBetween('transaction_date', [$from, $to])
            ->with('sale')
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    public function getOpeningBalance(int $customerId, Carbon $before): int
    {
        return (int) $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('transaction_date', '<', $before)
            ->orderBy('transaction_date', 'desc')
            ->value('balance_after') ?? 0;
    }

    public function updatePaidDateForSale(int $saleId, ?Carbon $paidDate): int
    {
        return $this->newQuery()
            ->where('sale_id', $saleId)
            ->where('type', 'charge')
            ->whereNull('paid_date')
            ->update([
                'paid_date' => $paidDate,
            ]);
    }

    public function getUnpaidChargesCount(): int
    {
        return $this->newQuery()
            ->where('type', 'charge')
            ->where('due_date', '<', Carbon::now())
            ->whereNull('paid_date')
            ->count();
    }
}
