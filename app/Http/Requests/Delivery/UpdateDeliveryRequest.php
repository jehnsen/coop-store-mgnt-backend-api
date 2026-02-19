<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryRequest extends FormRequest
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
            'customer_id' => 'nullable|string|exists:customers,uuid',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_city' => 'nullable|string|max:100',
            'delivery_province' => 'nullable|string|max:100',
            'delivery_postal_code' => 'nullable|string|max:10',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'delivery_instructions' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.sale_item_id' => 'required|integer|exists:sale_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDeliveryStatus($validator);
            $this->validateItemsBelongToSale($validator);
            $this->validateItemQuantities($validator);
        });
    }

    /**
     * Validate that delivery can be updated based on its status.
     */
    protected function validateDeliveryStatus(Validator $validator): void
    {
        $delivery = $this->route('delivery');

        if (!$delivery) {
            return;
        }

        $allowedStatuses = ['preparing', 'dispatched'];

        if (!in_array($delivery->status, $allowedStatuses)) {
            $validator->errors()->add(
                'status',
                "Delivery can only be updated when status is 'preparing' or 'dispatched'. Current status: {$delivery->status}."
            );
        }
    }

    /**
     * Validate that all items belong to the delivery's sale.
     */
    protected function validateItemsBelongToSale(Validator $validator): void
    {
        if (!$this->items) {
            return;
        }

        $delivery = $this->route('delivery');

        if (!$delivery || !$delivery->sale) {
            return;
        }

        $saleItemIds = $delivery->sale->saleItems()->pluck('id')->toArray();

        foreach ($this->items as $index => $item) {
            if (!in_array($item['sale_item_id'], $saleItemIds)) {
                $validator->errors()->add(
                    "items.{$index}.sale_item_id",
                    'The selected sale item does not belong to this delivery\'s sale.'
                );
            }
        }
    }

    /**
     * Validate that quantities don't exceed sale item quantities.
     */
    protected function validateItemQuantities(Validator $validator): void
    {
        if (!$this->items) {
            return;
        }

        $delivery = $this->route('delivery');

        if (!$delivery) {
            return;
        }

        foreach ($this->items as $index => $item) {
            $saleItem = \App\Models\SaleItem::find($item['sale_item_id']);

            if (!$saleItem) {
                continue;
            }

            // Check if quantity exceeds sale item quantity
            if ($item['quantity'] > $saleItem->quantity) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "The quantity cannot exceed the sale item quantity of {$saleItem->quantity}."
                );
            }

            // Check already delivered quantities (excluding current delivery)
            $alreadyDelivered = \App\Models\DeliveryItem::whereHas('delivery', function ($query) use ($delivery) {
                $query->where('id', '!=', $delivery->id)
                    ->whereNotIn('status', ['failed', 'cancelled']);
            })
            ->where('sale_item_id', $item['sale_item_id'])
            ->sum('quantity');

            $remainingQuantity = $saleItem->quantity - $alreadyDelivered;

            if ($item['quantity'] > $remainingQuantity) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "Only {$remainingQuantity} units are available for delivery (already delivered: {$alreadyDelivered})."
                );
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scheduled_date.after_or_equal' => 'The scheduled date must be today or later.',
            'items.*.sale_item_id.required' => 'Each item must have a sale item ID.',
            'items.*.sale_item_id.exists' => 'One or more sale items do not exist.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.min' => 'Item quantity must be at least 0.01.',
        ];
    }
}
