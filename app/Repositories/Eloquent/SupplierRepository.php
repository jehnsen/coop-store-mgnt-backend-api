<?php

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Models\Product;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function model(): string
    {
        return Supplier::class;
    }

    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('company_name', 'LIKE', "%{$query}%")
                    ->orWhere('contact_person', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->orWhere('contact_person_phone', 'LIKE', "%{$query}%")
                    ->orWhere('code', 'LIKE', "%{$query}%");
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getWithPurchaseOrderCount(): Collection
    {
        return $this->newQuery()
            ->withCount('purchaseOrders')
            ->orderBy('company_name')
            ->get();
    }

    public function getActive(): Collection
    {
        return $this->newQuery()
            ->where('is_active', true)
            ->orderBy('company_name')
            ->get();
    }

    public function findByCode(string $code): ?Supplier
    {
        return $this->newQuery()
            ->where('code', $code)
            ->first();
    }

    public function getSupplierProducts(string $supplierUuid): Collection
    {
        $supplier = $this->findByUuidOrFail($supplierUuid);

        return $supplier->products()
            ->with(['category', 'unit'])
            ->get();
    }

    public function linkProduct(string $supplierUuid, string $productUuid, array $data): bool
    {
        $supplier = $this->findByUuidOrFail($supplierUuid);
        $product = Product::where('uuid', $productUuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $supplier->products()->attach($product->id, $data);

        return true;
    }

    public function unlinkProduct(string $supplierUuid, string $productUuid): bool
    {
        $supplier = $this->findByUuidOrFail($supplierUuid);
        $product = Product::where('uuid', $productUuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        $supplier->products()->detach($product->id);

        return true;
    }

    public function getPriceHistory(string $supplierUuid, ?string $productUuid = null, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $supplier = $this->findByUuidOrFail($supplierUuid);

        $query = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'poi.purchase_order_id', '=', 'po.id')
            ->join('products as p', 'poi.product_id', '=', 'p.id')
            ->where('po.supplier_id', $supplier->id)
            ->whereIn('po.status', ['submitted', 'partial', 'received'])
            ->select(
                'p.uuid as product_uuid',
                'p.name as product_name',
                'p.sku as product_sku',
                'poi.unit_price',
                'poi.quantity_ordered',
                'po.order_date',
                'po.po_number'
            );

        // Filter by specific product if provided
        if ($productUuid) {
            $product = Product::where('uuid', $productUuid)
                ->where('store_id', Auth::user()->store_id)
                ->firstOrFail();

            $query->where('poi.product_id', $product->id);
        }

        // Filter by date range
        if ($fromDate) {
            $query->where('po.order_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('po.order_date', '<=', $toDate);
        }

        return $query->orderBy('po.order_date', 'desc')->get();
    }

    public function getSupplierStatistics(string $supplierUuid): array
    {
        $supplier = $this->findByUuidOrFail($supplierUuid);

        $totalPurchasesAmount = $supplier->purchaseOrders()
            ->whereIn('status', ['received', 'partial'])
            ->sum('total_amount');

        $lastPurchaseDate = $supplier->purchaseOrders()
            ->orderBy('order_date', 'desc')
            ->value('order_date');

        return [
            'total_purchases_amount' => $totalPurchasesAmount,
            'last_purchase_date' => $lastPurchaseDate,
        ];
    }

    public function getPriceComparisonReport(?int $productId = null): Collection
    {
        $storeId = Auth::user()->store_id;

        $query = DB::table('supplier_product')
            ->join('products', 'supplier_product.product_id', '=', 'products.id')
            ->join('suppliers', 'supplier_product.supplier_id', '=', 'suppliers.id')
            ->where('products.store_id', $storeId)
            ->select(
                'products.id as product_id',
                'products.uuid as product_uuid',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.cost_price as current_cost_price',
                'suppliers.id as supplier_id',
                'suppliers.code as supplier_code',
                'suppliers.company_name as supplier_name',
                'supplier_product.supplier_sku',
                'supplier_product.supplier_price',
                'supplier_product.lead_time_days',
                'supplier_product.minimum_order_quantity',
                'supplier_product.is_preferred'
            );

        if ($productId) {
            $query->where('products.id', $productId);
        }

        return $query->orderBy('products.name')
            ->orderBy('supplier_product.supplier_price')
            ->get();
    }

    public function getWithOutstanding(): Collection
    {
        return $this->newQuery()
            ->where('total_outstanding', '>', 0)
            ->with('payableTransactions')
            ->orderBy('total_outstanding', 'desc')
            ->get();
    }

    public function getWithOverdueInvoices(): Collection
    {
        return $this->newQuery()
            ->whereHas('payableTransactions', function ($query) {
                $query->where('type', 'invoice')
                    ->whereNull('paid_date')
                    ->where('due_date', '<', Carbon::now());
            })
            ->with(['payableTransactions' => function ($query) {
                $query->where('type', 'invoice')
                    ->whereNull('paid_date')
                    ->where('due_date', '<', Carbon::now())
                    ->orderBy('due_date', 'asc');
            }])
            ->get();
    }

    public function getAPOverviewStats(): array
    {
        $storeId = Auth::user()->store_id;

        $stats = $this->newQuery()
            ->selectRaw('
                COUNT(*) as total_suppliers,
                COUNT(CASE WHEN total_outstanding > 0 THEN 1 END) as suppliers_with_balance,
                SUM(total_outstanding) as total_outstanding,
                SUM(total_purchases) as total_purchases
            ')
            ->first();

        return [
            'total_suppliers' => (int) ($stats->total_suppliers ?? 0),
            'suppliers_with_balance' => (int) ($stats->suppliers_with_balance ?? 0),
            'total_outstanding' => (int) ($stats->total_outstanding ?? 0),
            'total_purchases' => (int) ($stats->total_purchases ?? 0),
            'average_payment_terms' => (int) $this->newQuery()->avg('payment_terms_days'),
        ];
    }
}
