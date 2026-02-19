<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\PurchaseOrder;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'required', 'exists:suppliers,uuid'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if PO is in draft status
            $purchaseOrder = PurchaseOrder::where('uuid', $this->route('uuid'))
                ->where('store_id', Auth::user()->store_id)
                ->first();

            if (!$purchaseOrder) {
                $validator->errors()->add('purchase_order', 'Purchase order not found.');
                return;
            }

            if ($purchaseOrder->status !== 'draft') {
                $validator->errors()->add(
                    'status',
                    'Only draft purchase orders can be updated. Current status: ' . $purchaseOrder->status
                );
                return;
            }

            // Validate products belong to store
            if (!$this->has('items')) {
                return;
            }

            $storeId = Auth::user()->store_id;

            foreach ($this->input('items', []) as $index => $item) {
                $product = Product::where('uuid', $item['product_id'] ?? null)
                    ->where('store_id', $storeId)
                    ->first();

                if (!$product) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'Product not found or does not belong to your store.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'expected_delivery_date.date' => 'Expected delivery date must be a valid date.',
            'expected_delivery_date.after_or_equal' => 'Expected delivery date cannot be in the past.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.product_id.required' => 'Product is required for all items.',
            'items.*.quantity.required' => 'Quantity is required for all items.',
            'items.*.quantity.numeric' => 'Quantity must be a number.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            'items.*.unit_cost.required' => 'Unit cost is required for all items.',
            'items.*.unit_cost.integer' => 'Unit cost must be a valid amount in centavos.',
            'items.*.unit_cost.min' => 'Unit cost cannot be negative.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
