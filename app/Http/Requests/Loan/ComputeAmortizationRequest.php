<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComputeAmortizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal_amount'   => ['required', 'numeric', 'min:0.01'],    // pesos
            'interest_rate'      => ['required', 'numeric', 'min:0.0001', 'max:1'], // monthly rate
            'term_months'        => ['required', 'integer', 'min:1', 'max:360'],
            'first_payment_date' => ['required', 'date'],
            'payment_interval'   => ['nullable', 'string', Rule::in(['weekly', 'semi_monthly', 'monthly'])],
        ];
    }

    public function messages(): array
    {
        return [
            'interest_rate.min' => 'Monthly interest rate must be greater than zero.',
            'interest_rate.max' => 'Monthly interest rate cannot exceed 100% (1.0).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_interval' => $this->input('payment_interval', 'monthly'),
        ]);
    }
}
