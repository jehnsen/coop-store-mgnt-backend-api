<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponse;

    /**
     * Display a paginated listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::withCount('products');

        // Search by name
        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $categories = $query->paginate($perPage);

        return $this->paginatedResponse(
            $categories->setCollection(
                $categories->getCollection()->map(fn($category) => new CategoryResource($category))
            ),
            'Categories retrieved successfully'
        );
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Auto-generate slug from name if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);

                // Ensure slug is unique
                $originalSlug = $data['slug'];
                $count = 1;
                while (Category::where('slug', $data['slug'])->exists()) {
                    $data['slug'] = $originalSlug . '-' . $count;
                    $count++;
                }
            }

            // Create category
            $category = Category::create($data);

            DB::commit();

            return $this->successResponse(
                new CategoryResource($category),
                'Category created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to create category: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Display the specified category.
     */
    public function show(string $uuid): JsonResponse
    {
        $category = Category::withCount('products')
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->successResponse(
            new CategoryResource($category),
            'Category retrieved successfully'
        );
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            $category = Category::where('uuid', $uuid)->firstOrFail();
            $data = $request->validated();

            // Auto-generate slug from name if name is updated and slug is not provided
            if (isset($data['name']) && !isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);

                // Ensure slug is unique (ignore current category)
                $originalSlug = $data['slug'];
                $count = 1;
                while (Category::where('slug', $data['slug'])->where('id', '!=', $category->id)->exists()) {
                    $data['slug'] = $originalSlug . '-' . $count;
                    $count++;
                }
            }

            // Update category
            $category->update($data);

            DB::commit();

            return $this->successResponse(
                new CategoryResource($category->fresh()),
                'Category updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to update category: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Remove the specified category (soft delete).
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $category = Category::withCount('products')
                ->where('uuid', $uuid)
                ->firstOrFail();

            // Check if category has products
            if ($category->products_count > 0) {
                return $this->errorResponse(
                    'Cannot delete category that has products. Please reassign or remove products first.',
                    ['products_count' => $category->products_count],
                    400
                );
            }

            $category->delete();

            return $this->successResponse(
                null,
                'Category deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete category: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Reorder categories by updating sort_order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*.uuid' => 'required|string|exists:categories,uuid',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $categories = $request->input('categories');

            foreach ($categories as $categoryData) {
                Category::where('uuid', $categoryData['uuid'])
                    ->update(['sort_order' => $categoryData['sort_order']]);
            }

            DB::commit();

            return $this->successResponse(
                ['updated_count' => count($categories)],
                'Categories reordered successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to reorder categories: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
