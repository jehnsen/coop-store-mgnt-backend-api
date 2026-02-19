<?php

namespace App\Repositories\Eloquent;

use App\Models\PayableTransaction;
use App\Repositories\Contracts\PayableTransactionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PayableTransactionRepository extends BaseRepository implements PayableTransactionRepositoryInterface
{
    protected function model(): string
    {
        return PayableTransaction::class;
    }

    public function getBySupplier(int $supplierId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->with(['purchaseOrder', 'user'])
            ->orderBy('transaction_date', 'desc')
            ->paginate($perPage);
    }

    public function getInvoices(?int $supplierId = null): Collection
    {
        $query = $this->newQuery()->where('type', 'invoice');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->with(['supplier', 'purchaseOrder'])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    public function getPayments(?int $supplierId = null): Collection
    {
        $query = $this->newQuery()->where('type', 'payment');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->with(['supplier'])
            ->orderBy('transaction_date', 'desc')
            ->get();
    }

    public function getOverdue(): Collection
    {
        return $this->newQuery()
            ->where('type', 'invoice')
            ->whereNull('paid_date')
            ->where('due_date', '<', Carbon::now())
            ->with(['supplier', 'purchaseOrder'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getAgingReport(): array
    {
        $storeId = Auth::user()->store_id;
        $today = Carbon::now();

        $transactions = $this->newQuery()
            ->where('type', 'invoice')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->with('supplier:id,uuid,name,code')
            ->get();

        $aging = [
            'current' => ['amount' => 0, 'count' => 0, 'suppliers' => []],
            '31-60' => ['amount' => 0, 'count' => 0, 'suppliers' => []],
            '61-90' => ['amount' => 0, 'count' => 0, 'suppliers' => []],
            'over-90' => ['amount' => 0, 'count' => 0, 'suppliers' => []],
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

            if (!in_array($transaction->supplier->uuid, array_column($aging[$bucket]['suppliers'], 'uuid'))) {
                $aging[$bucket]['suppliers'][] = [
                    'uuid' => $transaction->supplier->uuid,
                    'name' => $transaction->supplier->name,
                    'code' => $transaction->supplier->code,
                ];
            }
        }

        return $aging;
    }

    public function getAllocationHistory(int $purchaseOrderId): Collection
    {
        return $this->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    public function getUnpaidInvoices(int $supplierId): Collection
    {
        return $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->where('type', 'invoice')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getTotalOutstanding(int $supplierId): int
    {
        return (int) $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->where('type', 'invoice')
            ->whereNull('paid_date')
            ->where('is_reversed', false)
            ->sum('amount');
    }

    public function getDisbursementReport(Carbon $from, Carbon $to): array
    {
        // Daily disbursements
        $dailyDisbursements = $this->newQuery()
            ->where('type', 'payment')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                DATE(transaction_date) as date,
                payment_method,
                COUNT(*) as payment_count,
                SUM(ABS(amount)) as total_disbursed
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
                SUM(ABS(amount)) as total_disbursed
            ')
            ->groupBy('payment_method')
            ->get();

        // Overall summary
        $summary = $this->newQuery()
            ->where('type', 'payment')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('
                COUNT(*) as payment_count,
                SUM(ABS(amount)) as total_disbursed
            ')
            ->first();

        return [
            'summary' => [
                'total_disbursed' => (int) ($summary->total_disbursed ?? 0),
                'total_payments' => (int) ($summary->payment_count ?? 0),
            ],
            'by_method' => $methodSummary,
            'daily_disbursements' => $dailyDisbursements,
        ];
    }

    public function getStatementTransactions(int $supplierId, Carbon $from, Carbon $to): Collection
    {
        return $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->whereBetween('transaction_date', [$from, $to])
            ->with('purchaseOrder')
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    public function getOpeningBalance(int $supplierId, Carbon $before): int
    {
        return (int) $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->where('transaction_date', '<', $before)
            ->orderBy('transaction_date', 'desc')
            ->value('balance_after') ?? 0;
    }

    public function updatePaidDateForPurchaseOrder(int $purchaseOrderId, ?Carbon $paidDate): int
    {
        return $this->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('type', 'invoice')
            ->whereNull('paid_date')
            ->update([
                'paid_date' => $paidDate,
            ]);
    }

    public function getUnpaidInvoicesCount(): int
    {
        return $this->newQuery()
            ->where('type', 'invoice')
            ->where('due_date', '<', Carbon::now())
            ->whereNull('paid_date')
            ->count();
    }
}
