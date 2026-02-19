<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class DisburseLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disbursement_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'first_payment_date' => ['nullable', 'date', 'after:disbursement_date'],
            'reference_number'   => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_payment_date.after' => 'First payment date must be after the disbursement date.',
        ];
    }
}
