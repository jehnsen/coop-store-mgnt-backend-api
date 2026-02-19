<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends FormRequest
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
            'sale_id' => 'required|string|exists:sales,uuid',
            'customer_id' => 'nullable|string|exists:customers,uuid',
            'scheduled_date' => 'required|date|after_or_equal:today',
            'delivery_address' => 'required|string|max:500',
            'delivery_city' => 'nullable|string|max:100',
            'delivery_province' => 'nullable|string|max:100',
            'delivery_postal_code' => 'nullable|string|max:10',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'required|string|max:20',
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
            $this->validateSaleBelongsToStore($validator);
            $this->validateItemsBelongToSale($validator);
            $this->validateItemQuantities($validator);
        });
    }

    /**
     * Validate that the sale belongs to the authenticated user's store.
     */
    protected function validateSaleBelongsToStore(Validator $validator): void
    {
        $sale = \App\Models\Sale::where('uuid', $this->sale_id)->first();

        if ($sale && $sale->store_id !== auth()->user()->store_id) {
            $validator->errors()->add('sale_id', 'The selected sale does not belong to your store.');
        }
    }

    /**
     * Validate that all items belong to the specified sale.
     */
    protected function validateItemsBelongToSale(Validator $validator): void
    {
        if (!$this->items) {
            return;
        }

        $sale = \App\Models\Sale::where('uuid', $this->sale_id)->first();

        if (!$sale) {
            return;
        }

        $saleItemIds = $sale->saleItems()->pluck('id')->toArray();

        foreach ($this->items as $index => $item) {
            if (!in_array($item['sale_item_id'], $saleItemIds)) {
                $validator->errors()->add(
                    "items.{$index}.sale_item_id",
                    'The selected sale item does not belong to this sale.'
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

            // Check already delivered quantities
            $alreadyDelivered = \App\Models\DeliveryItem::whereHas('delivery', function ($query) {
                $query->whereNotIn('status', ['failed', 'cancelled']);
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
            'sale_id.required' => 'The sale is required.',
            'sale_id.exists' => 'The selected sale does not exist.',
            'scheduled_date.required' => 'The scheduled delivery date is required.',
            'scheduled_date.after_or_equal' => 'The scheduled date must be today or later.',
            'delivery_address.required' => 'The delivery address is required.',
            'contact_phone.required' => 'The contact phone number is required.',
            'items.*.sale_item_id.required' => 'Each item must have a sale item ID.',
            'items.*.sale_item_id.exists' => 'One or more sale items do not exist.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.min' => 'Item quantity must be at least 0.01.',
        ];
    }
}
