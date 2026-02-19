<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Criteria\SearchMultipleColumns;
use App\Repositories\Criteria\FilterByColumn;
use App\Repositories\Criteria\OrderBy;
use App\Repositories\Criteria\WithRelations;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    use ApiResponse;

    protected SupplierRepositoryInterface $supplierRepo;

    public function __construct(SupplierRepositoryInterface $supplierRepo)
    {
        $this->supplierRepo = $supplierRepo;
    }

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        // Apply search criteria
        if ($request->filled('search')) {
            $this->supplierRepo->pushCriteria(
                new SearchMultipleColumns(
                    $request->input('search'),
                    ['name', 'contact_person', 'phone']
                )
            );
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $this->supplierRepo->pushCriteria(
                new FilterByColumn('is_active', $request->boolean('is_active'))
            );
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $this->supplierRepo->pushCriteria(new OrderBy($sortBy, $sortOrder));

        // Get paginated results
        $perPage = $request->input('per_page', 15);
        $suppliers = $this->supplierRepo->paginate($perPage);

        // Load counts after pagination
        $suppliers->getCollection()->each->loadCount('purchaseOrders');

        return $this->paginatedResponse(
            $suppliers->setCollection(
                $suppliers->getCollection()->map(fn($supplier) => new SupplierResource($supplier))
            ),
            'Suppliers retrieved successfully'
        );
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierRepo->create([
            'store_id' => Auth::user()->store_id,
            'name' => $request->input('name'),
            'contact_person' => $request->input('contact_person'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'mobile' => $request->input('alternate_phone'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'province' => $request->input('province'),
            'postal_code' => $request->input('postal_code'),
            'tin' => $request->input('tin'),
            'payment_terms_days' => $request->input('payment_terms_days'),
            'is_active' => $request->input('is_active', true),
            'notes' => $request->input('notes'),
        ]);

        return $this->successResponse(
            new SupplierResource($supplier),
            'Supplier created successfully',
            201
        );
    }

    /**
     * Display the specified supplier.
     */
    public function show(string $uuid): JsonResponse
    {
        // Apply criteria for relationships and counts
        $this->supplierRepo->pushCriteria(
            new WithRelations(['purchaseOrders', 'products'])
        );

        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

        // Get statistics
        $statistics = $this->supplierRepo->getSupplierStatistics($uuid);
        $supplier->total_purchases_amount = $statistics['total_purchases_amount'];
        $supplier->last_purchase_date = $statistics['last_purchase_date'];

        // Add counts
        $supplier->loadCount(['purchaseOrders', 'products']);

        return $this->successResponse(
            new SupplierResource($supplier),
            'Supplier retrieved successfully'
        );
    }

    /**
     * Update the specified supplier.
     */
    public function update(UpdateSupplierRequest $request, string $uuid): JsonResponse
    {
        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

        $updateData = $request->only([
            'name',
            'contact_person',
            'email',
            'phone',
            'address',
            'city',
            'province',
            'postal_code',
            'tin',
            'payment_terms_days',
            'is_active',
            'notes',
        ]);

        if ($request->filled('alternate_phone')) {
            $updateData['mobile'] = $request->input('alternate_phone');
        }

        $supplier->update($updateData);

        return $this->successResponse(
            new SupplierResource($supplier->fresh()),
            'Supplier updated successfully'
        );
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);

        // Check if supplier has purchase orders
        $poCount = $supplier->purchaseOrders()->count();

        // Soft delete the supplier
        $supplier->delete();

        $message = 'Supplier deleted successfully';
        if ($poCount > 0) {
            $message .= " (had {$poCount} purchase orders)";
        }

        return $this->successResponse(null, $message);
    }

    /**
     * Get products supplied by this supplier.
     */
    public function products(string $uuid): JsonResponse
    {
        $products = $this->supplierRepo->getSupplierProducts($uuid);

        return $this->successResponse(
            ProductResource::collection($products),
            'Supplier products retrieved successfully'
        );
    }

    /**
     * Add a product to supplier.
     */
    public function addProduct(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'product_id' => ['required', 'string'],
            'supplier_price' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['nullable', 'boolean'],
        ]);

        // Check if already exists
        $supplier = $this->supplierRepo->findByUuidOrFail($uuid);
        $productUuid = $request->input('product_id');

        // Find product by UUID to verify it exists
        $product = \App\Models\Product::where('uuid', $productUuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        if ($supplier->products()->where('product_id', $product->id)->exists()) {
            return $this->errorResponse('Product is already associated with this supplier', null, 409);
        }

        // Link product using repository
        $this->supplierRepo->linkProduct($uuid, $productUuid, [
            'supplier_price' => $request->input('supplier_price'),
            'lead_time_days' => $request->input('lead_time_days'),
            'is_preferred' => $request->boolean('is_preferred', false),
        ]);

        return $this->successResponse(
            null,
            'Product added to supplier successfully'
        );
    }

    /**
     * Remove a product from supplier.
     */
    public function removeProduct(string $uuid, string $productUuid): JsonResponse
    {
        $this->supplierRepo->unlinkProduct($uuid, $productUuid);

        return $this->successResponse(
            null,
            'Product removed from supplier successfully'
        );
    }

    /**
     * Get price history for supplier's products.
     */
    public function priceHistory(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        // Use repository method to get price history
        $priceHistory = $this->supplierRepo->getPriceHistory(
            $uuid,
            $request->input('product_id'),
            $request->input('from_date'),
            $request->input('to_date')
        );

        // Convert unit_price from centavos to pesos
        $priceHistory = $priceHistory->map(function ($item) {
            $item->unit_price = $item->unit_price / 100;
            return $item;
        });

        return $this->successResponse(
            $priceHistory,
            'Price history retrieved successfully'
        );
    }
}
