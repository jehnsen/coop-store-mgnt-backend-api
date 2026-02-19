<?php

namespace App\Http\Requests\PatronageRefund;

use Illuminate\Foundation\Http\FormRequest;

class RecordPatronageDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method'   => ['required', 'in:cash,check,bank_transfer,savings_credit,gcash,maya,internal_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_date'        => ['nullable', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
