<?php

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;

class CreditSavingsInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_label'     => ['required', 'string', 'max:20'],  // e.g. "2026-01"
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }
}
