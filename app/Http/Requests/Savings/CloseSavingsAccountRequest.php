<?php

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_payment_method' => ['nullable', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check',
                'salary_deduction', 'internal_transfer',
            ])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'closed_date'      => ['nullable', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
