<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    protected function model(): string
    {
        return PurchaseOrder::class;
    }

    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('status', $status)
            ->with(['supplier', 'purchaseOrderItems.product'])
            ->orderBy('order_date', 'desc')
            ->paginate($perPage);
    }

    public function getBySupplier(int $supplierId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->with(['purchaseOrderItems.product'])
            ->orderBy('order_date', 'desc')
            ->paginate($perPage);
    }

    public function getByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->newQuery()
            ->whereBetween('order_date', [$from, $to])
            ->with(['supplier', 'purchaseOrderItems.product'])
            ->orderBy('order_date', 'desc')
            ->get();
    }

    public function findByPoNumber(string $poNumber): ?PurchaseOrder
    {
        return $this->newQuery()
            ->where('po_number', $poNumber)
            ->with(['supplier', 'purchaseOrderItems.product'])
            ->first();
    }

    public function getNextPoNumber(): string
    {
        $storeId = Auth::user()->store_id;
        $year = now()->year;
        $prefix = "PO-{$year}-";

        $lastPo = $this->newQuery()
            ->where('po_number', 'LIKE', "{$prefix}%")
            ->lockForUpdate()
            ->orderBy('po_number', 'desc')
            ->first();

        if ($lastPo) {
            $lastNumber = (int) substr($lastPo->po_number, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function getPendingReceiving(): Collection
    {
        return $this->newQuery()
            ->whereIn('status', ['submitted', 'partial'])
            ->with(['supplier', 'purchaseOrderItems.product'])
            ->orderBy('expected_delivery_date', 'asc')
            ->get();
    }

    public function getPurchasesBySupplier(Carbon $from, Carbon $to): Collection
    {
        return DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->where('purchase_orders.store_id', Auth::user()->store_id)
            ->whereIn('purchase_orders.status', ['submitted', 'received', 'partial'])
            ->whereBetween('purchase_orders.order_date', [$from, $to])
            ->select(
                'suppliers.company_name',
                DB::raw('COUNT(purchase_orders.id) as po_count'),
                DB::raw('SUM(purchase_orders.total_amount) as total_amount')
            )
            ->groupBy('suppliers.id', 'suppliers.company_name')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    public function getUnpaidBySupplier(int $supplierId, ?array $poUuids = null): Collection
    {
        $query = $this->newQuery()
            ->where('supplier_id', $supplierId)
            ->where('status', 'received') // Only received POs have invoices
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('payment_due_date', 'asc'); // FIFO by due date

        if ($poUuids) {
            $query->whereIn('uuid', $poUuids);
        }

        return $query->get();
    }
}
