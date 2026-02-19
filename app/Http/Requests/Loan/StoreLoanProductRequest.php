<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                 => ['required', 'string', 'max:20', Rule::unique('loan_products')->where(fn ($q) => $q->where('store_id', auth()->user()->store_id))],
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'loan_type'            => ['required', 'string', Rule::in(['term', 'emergency', 'salary', 'agricultural', 'livelihood'])],
            'interest_rate'        => ['required', 'numeric', 'min:0.0001', 'max:1'], // monthly rate
            'max_term_months'      => ['required', 'integer', 'min:1', 'max:360'],
            'min_amount'           => ['required', 'numeric', 'min:0'],   // pesos
            'max_amount'           => ['required', 'numeric', 'min:0.01', 'gte:min_amount'],
            'processing_fee_rate'  => ['nullable', 'numeric', 'min:0', 'max:1'],
            'service_fee'          => ['nullable', 'numeric', 'min:0'],   // pesos
            'requires_collateral'  => ['boolean'],
            'is_active'            => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'           => 'A loan product with this code already exists in your store.',
            'interest_rate.min'     => 'Monthly interest rate must be greater than zero.',
            'interest_rate.max'     => 'Monthly interest rate cannot exceed 100% (1.0).',
            'max_amount.gte'        => 'Maximum amount must be greater than or equal to the minimum amount.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_collateral' => $this->boolean('requires_collateral', false),
            'is_active'           => $this->boolean('is_active', true),
        ]);
    }
}
