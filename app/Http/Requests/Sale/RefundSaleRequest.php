<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundSaleRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array'],
            'items.*.sale_item_id' => ['required', 'integer', Rule::exists('sale_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['required', 'string', Rule::in(['cash', 'gcash', 'maya', 'bank_transfer', 'check', 'credit'])],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If items are provided, validate quantities don't exceed original sale item quantities
            if ($this->has('items') && is_array($this->input('items'))) {
                foreach ($this->input('items') as $index => $item) {
                    $saleItem = \App\Models\SaleItem::find($item['sale_item_id'] ?? null);

                    if ($saleItem) {
                        // Calculate already refunded quantity
                        $refundedQuantity = \App\Models\SaleItem::where('parent_sale_item_id', $saleItem->id)
                            ->where('quantity', '<', 0)
                            ->sum('quantity');
                        $refundedQuantity = abs($refundedQuantity);

                        $availableQuantity = $saleItem->quantity - $refundedQuantity;

                        if ($item['quantity'] > $availableQuantity) {
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                sprintf(
                                    'Refund quantity (%s) exceeds available quantity (%s). Already refunded: %s',
                                    $item['quantity'],
                                    $availableQuantity,
                                    $refundedQuantity
                                )
                            );
                        }
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.array' => 'Items must be an array.',
            'items.*.sale_item_id.required' => 'Sale item ID is required for each refund item.',
            'items.*.sale_item_id.exists' => 'One or more sale items do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each refund item.',
            'items.*.quantity.min' => 'Refund quantity must be at least 0.01.',
            'reason.required' => 'A reason is required for the refund.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
            'refund_method.required' => 'Refund method is required.',
            'refund_method.in' => 'Invalid refund method.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'items' => 'refund items',
            'items.*.sale_item_id' => 'sale item',
            'items.*.quantity' => 'refund quantity',
            'reason' => 'refund reason',
            'refund_method' => 'refund method',
        ];
    }
}
