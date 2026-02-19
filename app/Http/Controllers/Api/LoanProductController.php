<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loan\StoreLoanProductRequest;
use App\Http\Requests\Loan\UpdateLoanProductRequest;
use App\Http\Resources\LoanProductResource;
use App\Models\LoanProduct;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanProductController extends Controller
{
    use ApiResponse;

    /**
     * GET /loan-products
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoanProduct::where('store_id', Auth::user()->store_id);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('loan_type')) {
            $query->where('loan_type', $request->input('loan_type'));
        }

        if ($request->has('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
        }

        $products = $query->orderBy('name')->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $products->setCollection(
                $products->getCollection()->map(fn ($p) => new LoanProductResource($p))
            ),
            'Loan products retrieved successfully.'
        );
    }

    /**
     * POST /loan-products
     */
    public function store(StoreLoanProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Convert pesos to centavos
            $data['min_amount'] = (int) round(($data['min_amount'] ?? 0) * 100);
            $data['max_amount'] = (int) round($data['max_amount'] * 100);
            $data['service_fee'] = (int) round(($data['service_fee'] ?? 0) * 100);

            $product = LoanProduct::create($data);

            return $this->successResponse(
                new LoanProductResource($product),
                'Loan product created successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create loan product.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /loan-products/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $product = LoanProduct::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        return $this->successResponse(
            new LoanProductResource($product),
            'Loan product retrieved successfully.'
        );
    }

    /**
     * PUT /loan-products/{uuid}
     */
    public function update(UpdateLoanProductRequest $request, string $uuid): JsonResponse
    {
        $product = LoanProduct::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        try {
            $data = $request->validated();

            if (isset($data['min_amount'])) {
                $data['min_amount'] = (int) round($data['min_amount'] * 100);
            }
            if (isset($data['max_amount'])) {
                $data['max_amount'] = (int) round($data['max_amount'] * 100);
            }
            if (isset($data['service_fee'])) {
                $data['service_fee'] = (int) round($data['service_fee'] * 100);
            }

            $product->update($data);

            return $this->successResponse(
                new LoanProductResource($product->fresh()),
                'Loan product updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update loan product.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /loan-products/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $product = LoanProduct::where('uuid', $uuid)
            ->where('store_id', Auth::user()->store_id)
            ->firstOrFail();

        if ($product->loans()->whereIn('status', ['active', 'approved', 'disbursed'])->exists()) {
            return $this->errorResponse(
                'Cannot delete a loan product with active or approved loans.',
                [],
                422
            );
        }

        $product->delete();

        return $this->successResponse(null, 'Loan product deleted successfully.');
    }
}
