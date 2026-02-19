<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\PurchaseOrderItem;

class ReceivePurchaseOrderRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0.01'],
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('items')) {
                return;
            }

            foreach ($this->input('items', []) as $index => $item) {
                $purchaseOrderItem = PurchaseOrderItem::find($item['purchase_order_item_id'] ?? null);

                if (!$purchaseOrderItem) {
                    continue;
                }

                // Calculate remaining quantity
                $remainingQuantity = $purchaseOrderItem->quantity_ordered - $purchaseOrderItem->quantity_received;
                $quantityToReceive = $item['quantity_received'] ?? 0;

                // Validate quantity does not exceed remaining
                if ($quantityToReceive > $remainingQuantity) {
                    $validator->errors()->add(
                        "items.{$index}.quantity_received",
                        "Quantity to receive ({$quantityToReceive}) exceeds remaining quantity ({$remainingQuantity}) for this item."
                    );
                }

                // Ensure item belongs to the purchase order being received
                $poUuid = $this->route('uuid');
                if ($purchaseOrderItem->purchaseOrder->uuid !== $poUuid) {
                    $validator->errors()->add(
                        "items.{$index}.purchase_order_item_id",
                        "This item does not belong to the purchase order being received."
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
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item is required.',
            'items.*.purchase_order_item_id.required' => 'Purchase order item ID is required.',
            'items.*.purchase_order_item_id.integer' => 'Purchase order item ID must be a valid number.',
            'items.*.purchase_order_item_id.exists' => 'Purchase order item not found.',
            'items.*.quantity_received.required' => 'Quantity received is required.',
            'items.*.quantity_received.numeric' => 'Quantity received must be a number.',
            'items.*.quantity_received.min' => 'Quantity received must be at least 0.01.',
            'received_date.date' => 'Received date must be a valid date.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
