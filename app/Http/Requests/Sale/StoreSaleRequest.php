<?php

declare(strict_types=1);

namespace App\Http\Requests\Sale;

use App\Models\CustomerWallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * MPC additions:
     *   - 'wallet' added to the allowed payment methods list.
     *   - 'payments.*.wallet_uuid' is conditionally required when method = 'wallet'.
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'nullable',
                'string',
                Rule::exists('customers', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true)
                ),
            ],

            'price_tier' => ['required', 'string', Rule::in(['retail', 'wholesale', 'contractor'])],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => [
                'required',
                'string',
                Rule::exists('products', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true)
                ),
            ],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'       => ['required', 'integer', 'min:0'],
            'items.*.discount_type'    => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value'   => ['nullable', 'numeric', 'min:0'],

            'discount_type'  => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],

            'payments'                        => ['required', 'array', 'min:1'],
            'payments.*.method'               => [
                'required',
                'string',
                // MPC: 'wallet' added alongside existing payment methods
                Rule::in(['cash', 'gcash', 'maya', 'bank_transfer', 'check', 'credit', 'wallet']),
            ],
            'payments.*.amount'               => ['required', 'integer', 'min:1'],
            'payments.*.reference_number'     => ['nullable', 'string', 'max:100'],
            // MPC: wallet_uuid is required when method = 'wallet' (enforced in withValidator)
            'payments.*.wallet_uuid'          => ['nullable', 'string'],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Cross-field validation that cannot be expressed as simple rule arrays.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $payments         = $this->input('payments', []);
            $hasCreditPayment = collect($payments)->contains('method', 'credit');
            $walletPayments   = collect($payments)->where('method', 'wallet');

            // -----------------------------------------------------------------
            // Rule: credit or wallet payments require a customer
            // -----------------------------------------------------------------
            if (($hasCreditPayment || $walletPayments->isNotEmpty()) && empty($this->input('customer_id'))) {
                $validator->errors()->add(
                    'customer_id',
                    'Customer is required when using credit or wallet payment methods.'
                );
            }

            // -----------------------------------------------------------------
            // MPC Rule: every wallet payment must carry a valid wallet_uuid
            //           that belongs to the specified customer
            // -----------------------------------------------------------------
            if ($walletPayments->isNotEmpty() && !$validator->errors()->has('customer_id')) {
                $customerId = $this->resolveCustomerId();

                $seenWalletUuids = [];

                foreach ($payments as $index => $paymentRow) {
                    if (($paymentRow['method'] ?? '') !== 'wallet') {
                        continue;
                    }

                    $walletUuid = $paymentRow['wallet_uuid'] ?? null;

                    // wallet_uuid must be present
                    if (empty($walletUuid)) {
                        $validator->errors()->add(
                            "payments.{$index}.wallet_uuid",
                            'A wallet_uuid is required when payment method is "wallet".'
                        );
                        continue;
                    }

                    // No duplicate wallet entries within the same sale
                    if (in_array($walletUuid, $seenWalletUuids, strict: true)) {
                        $validator->errors()->add(
                            "payments.{$index}.wallet_uuid",
                            "Duplicate wallet \"{$walletUuid}\": each wallet may only appear once per sale."
                        );
                        continue;
                    }

                    $seenWalletUuids[] = $walletUuid;

                    // Wallet must exist, be active, and belong to the customer
                    if ($customerId !== null) {
                        $wallet = CustomerWallet::where('uuid', $walletUuid)
                            ->where('customer_id', $customerId)
                            ->where('status', 'active')
                            ->first();

                        if ($wallet === null) {
                            $validator->errors()->add(
                                "payments.{$index}.wallet_uuid",
                                "Wallet \"{$walletUuid}\" not found, frozen, or does not belong to this customer."
                            );
                        }
                    }
                }
            }

            // -----------------------------------------------------------------
            // Rule: item-level percentage discounts ≤ 100 %
            // -----------------------------------------------------------------
            foreach ($this->input('items', []) as $index => $item) {
                if (($item['discount_type'] ?? '') === 'percentage'
                    && !empty($item['discount_value'])
                    && $item['discount_value'] > 100
                ) {
                    $validator->errors()->add(
                        "items.{$index}.discount_value",
                        'Percentage discount cannot exceed 100%.'
                    );
                }
            }

            // -----------------------------------------------------------------
            // Rule: order-level percentage discount ≤ 100 %
            // -----------------------------------------------------------------
            if ($this->input('discount_type') === 'percentage'
                && !empty($this->input('discount_value'))
                && $this->input('discount_value') > 100
            ) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
            }

            // -----------------------------------------------------------------
            // Rule: payment total must match calculated sale total
            // -----------------------------------------------------------------
            $this->validatePaymentTotal($validator);
        });
    }

    /**
     * Resolve the numeric customer ID from the customer UUID in the request.
     * Returns null when customer_id is absent or the customer cannot be found.
     */
    private function resolveCustomerId(): ?int
    {
        $customerUuid = $this->input('customer_id');

        if (empty($customerUuid)) {
            return null;
        }

        $customer = \App\Models\Customer::where('uuid', $customerUuid)
            ->where('store_id', auth()->user()->store_id)
            ->first();

        return $customer?->id;
    }

    /**
     * Validate that the sum of all payment amounts covers the calculated total.
     */
    protected function validatePaymentTotal($validator): void
    {
        try {
            $items    = $this->input('items', []);
            $subtotal = 0;

            foreach ($items as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];

                if (!empty($item['discount_type']) && !empty($item['discount_value'])) {
                    $lineTotal -= $item['discount_type'] === 'percentage'
                        ? $lineTotal * ($item['discount_value'] / 100)
                        : $item['discount_value'] * 100;
                }

                $subtotal += $lineTotal;
            }

            $totalAfterDiscount = $subtotal;

            if (!empty($this->input('discount_type')) && !empty($this->input('discount_value'))) {
                $totalAfterDiscount -= $this->input('discount_type') === 'percentage'
                    ? $subtotal * ($this->input('discount_value') / 100)
                    : $this->input('discount_value') * 100;
            }

            // MPC: member flag suppresses VAT at the request-validation layer
            $isMember  = $this->resolveMemberFlag();
            $store     = auth()->user()->store;
            $vatRate   = $store->vat_rate      ?? 12;
            $vatInclusive = $store->vat_inclusive ?? true;

            if (!$isMember && $vatRate > 0) {
                if (!$vatInclusive) {
                    $totalAfterDiscount += $totalAfterDiscount * ($vatRate / 100);
                }
            }

            $calculatedTotal = (int) round($totalAfterDiscount);
            $paymentTotal    = (int) collect($this->input('payments', []))->sum('amount');
            $difference      = abs($calculatedTotal - $paymentTotal);

            if ($difference > 1) {
                $validator->errors()->add('payments', sprintf(
                    'Payment total (₱%s) does not match sale total (₱%s). Difference: ₱%s',
                    number_format($paymentTotal / 100, 2),
                    number_format($calculatedTotal / 100, 2),
                    number_format($difference / 100, 2),
                ));
            }
        } catch (\Throwable) {
            // Let the service layer handle unexpected data; do not block the request.
        }
    }

    /**
     * Determine whether the customer in the request is a cooperative member.
     * Returns false when customer_id is absent or the customer cannot be found.
     */
    private function resolveMemberFlag(): bool
    {
        $customerId = $this->resolveCustomerId();

        if ($customerId === null) {
            return false;
        }

        return (bool) \App\Models\Customer::where('id', $customerId)
            ->value('is_member');
    }

    public function messages(): array
    {
        return [
            'customer_id.exists'             => 'The selected customer does not exist or is inactive.',
            'price_tier.required'            => 'Please select a price tier.',
            'price_tier.in'                  => 'Invalid price tier. Must be retail, wholesale, or contractor.',
            'items.required'                 => 'At least one item is required to create a sale.',
            'items.min'                      => 'At least one item is required to create a sale.',
            'items.*.product_id.required'    => 'Product is required for each item.',
            'items.*.product_id.exists'      => 'One or more selected products do not exist or are inactive.',
            'items.*.quantity.required'      => 'Quantity is required for each item.',
            'items.*.quantity.min'           => 'Quantity must be at least 0.01.',
            'items.*.unit_price.required'    => 'Unit price is required for each item.',
            'items.*.unit_price.min'         => 'Unit price cannot be negative.',
            'items.*.discount_type.in'       => 'Discount type must be percentage or fixed.',
            'items.*.discount_value.min'     => 'Discount value cannot be negative.',
            'discount_type.in'               => 'Order discount type must be percentage or fixed.',
            'discount_value.min'             => 'Order discount value cannot be negative.',
            'payments.required'              => 'At least one payment method is required.',
            'payments.min'                   => 'At least one payment method is required.',
            'payments.*.method.required'     => 'Payment method is required for each payment.',
            'payments.*.method.in'           => 'Invalid payment method. Allowed: cash, gcash, maya, bank_transfer, check, credit, wallet.',
            'payments.*.amount.required'     => 'Payment amount is required.',
            'payments.*.amount.min'          => 'Payment amount must be at least 1 centavo.',
            'payments.*.reference_number.max'=> 'Reference number cannot exceed 100 characters.',
            'notes.max'                      => 'Notes cannot exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id'              => 'customer',
            'price_tier'               => 'price tier',
            'items.*.product_id'       => 'product',
            'items.*.quantity'         => 'quantity',
            'items.*.unit_price'       => 'unit price',
            'items.*.discount_type'    => 'item discount type',
            'items.*.discount_value'   => 'item discount value',
            'discount_type'            => 'order discount type',
            'discount_value'           => 'order discount value',
            'payments.*.method'        => 'payment method',
            'payments.*.amount'        => 'payment amount',
            'payments.*.wallet_uuid'   => 'wallet',
            'payments.*.reference_number' => 'reference number',
            'notes'                    => 'notes',
        ];
    }
}
