<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordPaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,gcash,maya,bank_transfer,check'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['exists:sales,uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.integer' => 'Payment amount must be a valid amount in centavos.',
            'amount.min' => 'Payment amount must be at least 1 centavo.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be one of: cash, gcash, maya, bank_transfer, check.',
            'reference_number.max' => 'Reference number cannot exceed 100 characters.',
            'payment_date.date' => 'Payment date must be a valid date.',
            'invoice_ids.array' => 'Invoice IDs must be an array.',
            'invoice_ids.*.exists' => 'One or more selected invoices do not exist.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $customer = $this->route('uuid')
                ? \App\Models\Customer::where('uuid', $this->route('uuid'))->first()
                : null;

            if ($customer) {
                // Convert amount from centavos to pesos for comparison with customer's total_outstanding
                $amountInCentavos = $this->input('amount');
                $totalOutstandingInCentavos = $customer->getRawOriginal('total_outstanding');

                if ($amountInCentavos > $totalOutstandingInCentavos) {
                    $validator->errors()->add(
                        'amount',
                        'Payment amount cannot exceed customer\'s total outstanding balance of ₱' . number_format($customer->total_outstanding, 2)
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set payment_date to now if not provided
        if (!$this->has('payment_date')) {
            $this->merge([
                'payment_date' => now()->format('Y-m-d'),
            ]);
        }
    }
}
