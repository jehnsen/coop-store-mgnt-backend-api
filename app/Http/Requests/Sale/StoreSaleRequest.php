<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'customer_id' => ['nullable', 'string', Rule::exists('customers', 'uuid')->where(function ($query) {
                $query->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true);
            })],
            'price_tier' => ['required', 'string', Rule::in(['retail', 'wholesale', 'contractor'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string', Rule::exists('products', 'uuid')->where(function ($query) {
                $query->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true);
            })],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', Rule::in(['cash', 'gcash', 'maya', 'bank_transfer', 'check', 'credit'])],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If any payment method is 'credit', customer_id is required
            $payments = $this->input('payments', []);
            $hasCreditPayment = collect($payments)->contains('method', 'credit');

            if ($hasCreditPayment && empty($this->input('customer_id'))) {
                $validator->errors()->add('customer_id', 'Customer is required when using credit payment method.');
            }

            // Validate item discount values
            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (!empty($item['discount_type']) && $item['discount_type'] === 'percentage') {
                    if (!empty($item['discount_value']) && $item['discount_value'] > 100) {
                        $validator->errors()->add("items.{$index}.discount_value", 'Percentage discount cannot exceed 100%.');
                    }
                }
            }

            // Validate order-level discount
            if (!empty($this->input('discount_type')) && $this->input('discount_type') === 'percentage') {
                if (!empty($this->input('discount_value')) && $this->input('discount_value') > 100) {
                    $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
                }
            }

            // Validate total payments match calculated total
            $this->validatePaymentTotal($validator);
        });
    }

    /**
     * Validate that payment total matches calculated sale total.
     */
    protected function validatePaymentTotal($validator): void
    {
        try {
            // Calculate line totals
            $items = $this->input('items', []);
            $subtotal = 0;

            foreach ($items as $item) {
                $lineTotal = ($item['quantity'] * $item['unit_price']);

                // Apply item-level discount
                if (!empty($item['discount_type']) && !empty($item['discount_value'])) {
                    if ($item['discount_type'] === 'percentage') {
                        $lineTotal = $lineTotal - ($lineTotal * ($item['discount_value'] / 100));
                    } else {
                        $lineTotal = $lineTotal - ($item['discount_value'] * 100); // Convert to centavos
                    }
                }

                $subtotal += $lineTotal;
            }

            // Apply order-level discount
            $totalAfterDiscount = $subtotal;
            if (!empty($this->input('discount_type')) && !empty($this->input('discount_value'))) {
                if ($this->input('discount_type') === 'percentage') {
                    $totalAfterDiscount = $subtotal - ($subtotal * ($this->input('discount_value') / 100));
                } else {
                    $totalAfterDiscount = $subtotal - ($this->input('discount_value') * 100); // Convert to centavos
                }
            }

            // Get VAT settings from store
            $store = auth()->user()->store;
            $vatRate = $store->vat_rate ?? 12;
            $vatInclusive = $store->vat_inclusive ?? true;

            // Calculate VAT
            if ($vatInclusive) {
                $vatAmount = $totalAfterDiscount * ($vatRate / (100 + $vatRate));
            } else {
                $vatAmount = $totalAfterDiscount * ($vatRate / 100);
                $totalAfterDiscount += $vatAmount;
            }

            // Round to nearest centavo
            $calculatedTotal = round($totalAfterDiscount);

            // Sum payment amounts
            $payments = $this->input('payments', []);
            $paymentTotal = collect($payments)->sum('amount');

            // Allow 1 centavo tolerance for rounding differences
            $difference = abs($calculatedTotal - $paymentTotal);
            if ($difference > 1) {
                $validator->errors()->add('payments', sprintf(
                    'Payment total (₱%s) does not match sale total (₱%s). Difference: ₱%s',
                    number_format($paymentTotal / 100, 2),
                    number_format($calculatedTotal / 100, 2),
                    number_format($difference / 100, 2)
                ));
            }
        } catch (\Exception $e) {
            // If calculation fails, let it pass and let the service handle it
            // This prevents validation from breaking on unexpected data
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer does not exist or is inactive.',
            'price_tier.required' => 'Please select a price tier.',
            'price_tier.in' => 'Invalid price tier. Must be retail, wholesale, or contractor.',
            'items.required' => 'At least one item is required to create a sale.',
            'items.min' => 'At least one item is required to create a sale.',
            'items.*.product_id.required' => 'Product is required for each item.',
            'items.*.product_id.exists' => 'One or more selected products do not exist or are inactive.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be at least 0.01.',
            'items.*.unit_price.required' => 'Unit price is required for each item.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
            'items.*.discount_type.in' => 'Discount type must be percentage or fixed.',
            'items.*.discount_value.min' => 'Discount value cannot be negative.',
            'discount_type.in' => 'Order discount type must be percentage or fixed.',
            'discount_value.min' => 'Order discount value cannot be negative.',
            'payments.required' => 'At least one payment method is required.',
            'payments.min' => 'At least one payment method is required.',
            'payments.*.method.required' => 'Payment method is required for each payment.',
            'payments.*.method.in' => 'Invalid payment method.',
            'payments.*.amount.required' => 'Payment amount is required.',
            'payments.*.amount.min' => 'Payment amount must be at least 1 centavo.',
            'payments.*.reference_number.max' => 'Reference number cannot exceed 100 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'price_tier' => 'price tier',
            'items' => 'items',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
            'items.*.discount_type' => 'item discount type',
            'items.*.discount_value' => 'item discount value',
            'discount_type' => 'order discount type',
            'discount_value' => 'order discount value',
            'payments' => 'payments',
            'payments.*.method' => 'payment method',
            'payments.*.amount' => 'payment amount',
            'payments.*.reference_number' => 'reference number',
            'notes' => 'notes',
        ];
    }
}
