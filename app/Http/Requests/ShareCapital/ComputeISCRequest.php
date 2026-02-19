<?php

namespace App\Http\Requests\ShareCapital;

use Illuminate\Foundation\Http\FormRequest;

class ComputeISCRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'dividend_rate' => ['required', 'numeric', 'min:0.0001', 'max:1'], // e.g. 0.12 = 12%
        ];
    }

    public function messages(): array
    {
        return [
            'dividend_rate.min' => 'Dividend rate must be greater than zero.',
            'dividend_rate.max' => 'Dividend rate cannot exceed 100% (1.0).',
        ];
    }
}
