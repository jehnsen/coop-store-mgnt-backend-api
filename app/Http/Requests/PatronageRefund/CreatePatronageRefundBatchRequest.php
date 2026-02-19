<?php

namespace App\Http\Requests\PatronageRefund;

use Illuminate\Foundation\Http\FormRequest;

class CreatePatronageRefundBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_label'       => ['required', 'string', 'max:50'],
            'period_from'        => ['required', 'date'],
            'period_to'          => ['required', 'date', 'after_or_equal:period_from'],
            'computation_method' => ['required', 'in:rate_based,pool_based'],
            'pr_rate'            => ['required_if:computation_method,rate_based', 'nullable', 'numeric', 'min:0.000001', 'max:1'],
            'pr_fund'            => ['required_if:computation_method,pool_based', 'nullable', 'numeric', 'min:0.01'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pr_rate.required_if' => 'A PR rate is required for rate-based computation.',
            'pr_fund.required_if' => 'A PR fund amount is required for pool-based computation.',
        ];
    }
}
