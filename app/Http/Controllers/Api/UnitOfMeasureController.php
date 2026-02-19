<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\UnitOfMeasure;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitOfMeasureController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of all units (not paginated since small dataset).
     */
    public function index(): JsonResponse
    {
        $units = UnitOfMeasure::orderBy('name', 'asc')->get();

        return $this->successResponse(
            UnitResource::collection($units),
            'Units of measure retrieved successfully'
        );
    }

    /**
     * Store a newly created unit.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units_of_measure')->where(function ($query) use ($request) {
                    return $query->where('store_id', $request->user()->store_id);
                })
            ],
            'abbreviation' => ['required', 'string', 'max:10'],
        ], [
            'name.required' => 'Unit name is required.',
            'name.unique' => 'This unit name already exists in your store.',
            'name.max' => 'Unit name cannot exceed 50 characters.',
            'abbreviation.required' => 'Abbreviation is required.',
            'abbreviation.max' => 'Abbreviation cannot exceed 10 characters.',
        ]);

        try {
            DB::beginTransaction();

            $unit = UnitOfMeasure::create([
                'name' => $request->input('name'),
                'abbreviation' => $request->input('abbreviation'),
                'store_id' => $request->user()->store_id,
            ]);

            DB::commit();

            return $this->successResponse(
                new UnitResource($unit),
                'Unit of measure created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to create unit of measure: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Display the specified unit.
     */
    public function show(int $id): JsonResponse
    {
        $unit = UnitOfMeasure::findOrFail($id);

        return $this->successResponse(
            new UnitResource($unit),
            'Unit of measure retrieved successfully'
        );
    }

    /**
     * Update the specified unit.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $unit = UnitOfMeasure::findOrFail($id);

        $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('units_of_measure')->where(function ($query) use ($request) {
                    return $query->where('store_id', $request->user()->store_id);
                })->ignore($unit->id)
            ],
            'abbreviation' => ['sometimes', 'required', 'string', 'max:10'],
        ], [
            'name.required' => 'Unit name is required.',
            'name.unique' => 'This unit name already exists in your store.',
            'name.max' => 'Unit name cannot exceed 50 characters.',
            'abbreviation.required' => 'Abbreviation is required.',
            'abbreviation.max' => 'Abbreviation cannot exceed 10 characters.',
        ]);

        try {
            DB::beginTransaction();

            $unit->update($request->only(['name', 'abbreviation']));

            DB::commit();

            return $this->successResponse(
                new UnitResource($unit->fresh()),
                'Unit of measure updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to update unit of measure: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $unit = UnitOfMeasure::findOrFail($id);

            // Check if unit is being used by any products
            $productsCount = $unit->products()->count();

            if ($productsCount > 0) {
                return $this->errorResponse(
                    'Cannot delete unit that is being used by products. Please reassign products first.',
                    ['products_count' => $productsCount],
                    400
                );
            }

            $unit->delete();

            return $this->successResponse(
                null,
                'Unit of measure deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete unit of measure: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
