<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uuid = $this->route('uuid');

        return [
            'code'                => ['sometimes', 'string', 'max:20', Rule::unique('loan_products')->where(fn ($q) => $q->where('store_id', auth()->user()->store_id))->whereNot(fn ($q) => $q->where('uuid', $uuid))],
            'name'                => ['sometimes', 'string', 'max:100'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'loan_type'           => ['sometimes', 'string', Rule::in(['term', 'emergency', 'salary', 'agricultural', 'livelihood'])],
            'interest_rate'       => ['sometimes', 'numeric', 'min:0.0001', 'max:1'],
            'max_term_months'     => ['sometimes', 'integer', 'min:1', 'max:360'],
            'min_amount'          => ['sometimes', 'numeric', 'min:0'],
            'max_amount'          => ['sometimes', 'numeric', 'min:0.01'],
            'processing_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'service_fee'         => ['nullable', 'numeric', 'min:0'],
            'requires_collateral' => ['boolean'],
            'is_active'           => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('requires_collateral')) {
            $this->merge(['requires_collateral' => $this->boolean('requires_collateral')]);
        }
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}
