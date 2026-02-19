<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\AdjustStockRequest;
use App\Http\Requests\Product\BulkUpdateRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Criteria\ActiveOnly;
use App\Repositories\Criteria\FilterByColumn;
use App\Repositories\Criteria\LowStockProducts;
use App\Repositories\Criteria\OrderBy;
use App\Repositories\Criteria\SearchMultipleColumns;
use App\Repositories\Criteria\WithRelations;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponse;

    protected ProductRepositoryInterface $productRepo;
    protected InventoryService $inventoryService;

    public function __construct(
        ProductRepositoryInterface $productRepo,
        InventoryService $inventoryService
    ) {
        $this->productRepo = $productRepo;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a paginated listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        // Apply eager loading
        $this->productRepo->pushCriteria(new WithRelations(['category', 'unit']));

        // Search by name, sku, or barcode
        if ($request->has('q')) {
            $this->productRepo->pushCriteria(
                new SearchMultipleColumns($request->input('q'), ['name', 'sku', 'barcode'])
            );
        }

        // Filter by category
        if ($request->has('category_id')) {
            $this->productRepo->pushCriteria(
                new FilterByColumn('category_id', $request->input('category_id'))
            );
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $this->productRepo->pushCriteria(
                new FilterByColumn('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN))
            );
        }

        // Filter by low stock
        if ($request->boolean('low_stock')) {
            $this->productRepo->pushCriteria(new LowStockProducts());
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $this->productRepo->pushCriteria(new OrderBy($sortBy, $sortOrder));

        // Pagination
        $perPage = $request->input('per_page', 15);
        $products = $this->productRepo->paginate($perPage);

        return $this->paginatedResponse(
            $products->setCollection(
                $products->getCollection()->map(fn($product) => new ProductResource($product))
            ),
            'Products retrieved successfully'
        );
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Auto-generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = 'SKU-' . time() . '-' . strtoupper(Str::random(6));
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
                $data['image_path'] = $imagePath;
            }

            // Create product
            $product = $this->productRepo->create($data);

            DB::commit();

            return $this->successResponse(
                new ProductResource($product->load(['category', 'unit'])),
                'Product created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to create product: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Display the specified product.
     */
    public function show(string $uuid): JsonResponse
    {
        $this->productRepo->pushCriteria(new WithRelations(['category', 'unit', 'stockByBranch.branch']));
        $product = $this->productRepo->findByUuidOrFail($uuid);

        return $this->successResponse(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            $product = $this->productRepo->findByUuidOrFail($uuid);
            $data = $request->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($product->image_path) {
                    Storage::disk('public')->delete($product->image_path);
                }

                $imagePath = $request->file('image')->store('products', 'public');
                $data['image_path'] = $imagePath;
            }

            // Update product
            $product->update($data);

            DB::commit();

            return $this->successResponse(
                new ProductResource($product->load(['category', 'unit'])),
                'Product updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to update product: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Remove the specified product (soft delete).
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $product = $this->productRepo->findByUuidOrFail($uuid);
            $product->delete();

            return $this->successResponse(
                null,
                'Product deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete product: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Fast search for POS - returns minimal data.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $search = $request->input('q');

        // Apply criteria for active products and search
        $this->productRepo->pushCriteria(new ActiveOnly());
        $this->productRepo->pushCriteria(
            new SearchMultipleColumns($search, ['name', 'sku', 'barcode'])
        );

        // Get first 20 results
        $products = $this->productRepo->paginate(20, ['uuid', 'name', 'sku', 'barcode', 'retail_price', 'current_stock', 'image_path']);

        $results = $products->map(function ($product) {
            return [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => $product->retail_price / 100,
                'stock' => $product->current_stock,
                'image_url' => $product->image_path ? Storage::url($product->image_path) : null,
            ];
        });

        return $this->successResponse(
            $results,
            'Search completed successfully'
        );
    }

    /**
     * Lookup product by barcode.
     */
    public function barcode(string $barcode): JsonResponse
    {
        $product = $this->productRepo->findByBarcode($barcode);

        if (!$product) {
            return $this->errorResponse(
                'Product not found with this barcode',
                null,
                404
            );
        }

        return $this->successResponse(
            new ProductResource($product),
            'Product found successfully'
        );
    }

    /**
     * Adjust product stock.
     * Note: This validates the request. Actual stock logic will be in InventoryService.
     */
    public function adjustStock(AdjustStockRequest $request, string $uuid): JsonResponse
    {
        try {
            $product = $this->productRepo->findByUuidOrFail($uuid);
            $data = $request->validated();

            // For now, we'll do a simple adjustment
            // In the next phase, this will be delegated to InventoryService
            DB::beginTransaction();

            $currentStock = $product->current_stock ?? 0;

            switch ($data['adjustment_type']) {
                case 'add':
                    $newStock = $currentStock + $data['quantity'];
                    break;
                case 'subtract':
                    $newStock = $currentStock - $data['quantity'];
                    break;
                case 'set':
                    $newStock = $data['quantity'];
                    break;
                default:
                    throw new \Exception('Invalid adjustment type');
            }

            // Check negative stock
            if (!$product->allow_negative_stock && $newStock < 0) {
                return $this->errorResponse(
                    'Stock adjustment would result in negative stock. This product does not allow negative stock.',
                    null,
                    400
                );
            }

            $product->update(['current_stock' => $newStock]);

            DB::commit();

            return $this->successResponse(
                [
                    'product' => new ProductResource($product->fresh(['category', 'unit'])),
                    'previous_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'adjustment' => $data['quantity'],
                    'type' => $data['adjustment_type'],
                ],
                'Stock adjusted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to adjust stock: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Get stock adjustment history for a product.
     */
    public function stockHistory(Request $request, string $uuid): JsonResponse
    {
        $product = $this->productRepo->findByUuidOrFail($uuid);

        // Parse optional date range filters
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;
        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        // Get stock history from InventoryService
        $history = $this->inventoryService->getStockHistory($product, $startDate, $endDate);

        return $this->successResponse([
            'product' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => $product->current_stock,
            ],
            'history' => StockAdjustmentResource::collection($history),
            'total_records' => $history->count(),
        ], 'Stock history retrieved successfully');
    }

    /**
     * Get products with low stock (below reorder point).
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->productRepo->pushCriteria(new WithRelations(['category', 'unit']));
        $this->productRepo->pushCriteria(new LowStockProducts());

        $perPage = $request->input('per_page', 15);
        $products = $this->productRepo->paginate($perPage);

        return $this->paginatedResponse(
            $products->setCollection(
                $products->getCollection()->map(fn($product) => new ProductResource($product))
            ),
            'Low stock products retrieved successfully'
        );
    }

    /**
     * Bulk update multiple products.
     */
    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $productIds = $data['product_ids'];
            $updates = $data['updates'];

            // Update products using repository
            // We need to use raw query for bulk update as repository doesn't have this method yet
            $this->productRepo->resetCriteria();
            Product::whereIn('uuid', $productIds)->update($updates);

            DB::commit();

            return $this->successResponse(
                [
                    'updated_count' => count($productIds),
                    'updates' => $updates,
                ],
                'Products updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to bulk update products: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
