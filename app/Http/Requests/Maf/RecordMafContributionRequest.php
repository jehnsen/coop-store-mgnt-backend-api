<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMafContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'    => ['required', 'string', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'salary_deduction',
            ])],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'contribution_date' => ['nullable', 'date', 'before_or_equal:today'],
            'period_year'       => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month'      => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'            => 'Contribution amount is required.',
            'amount.min'                 => 'Contribution amount must be at least ₱0.01.',
            'payment_method.required'    => 'Payment method is required.',
            'payment_method.in'          => 'Invalid payment method.',
            'contribution_date.before_or_equal' => 'Contribution date cannot be in the future.',
            'period_year.required'       => 'Period year is required.',
            'period_month.min'           => 'Period month must be between 1 and 12.',
            'period_month.max'           => 'Period month must be between 1 and 12.',
        ];
    }

    public function attributes(): array
    {
        return [
            'period_year'       => 'contribution period year',
            'period_month'      => 'contribution period month',
            'contribution_date' => 'contribution date',
        ];
    }
}
