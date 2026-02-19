<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayMafClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method'   => ['required', 'string', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'salary_deduction',
            ])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date'     => ['nullable', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required'           => 'Payment method is required.',
            'payment_method.in'                 => 'Invalid payment method.',
            'payment_date.before_or_equal'      => 'Payment date cannot be in the future.',
        ];
    }
}
