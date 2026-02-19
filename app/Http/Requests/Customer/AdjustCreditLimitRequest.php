<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class AdjustCreditLimitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_limit.required' => 'Credit limit is required.',
            'credit_limit.integer' => 'Credit limit must be a valid amount in centavos.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
            'reason.required' => 'Reason for credit limit adjustment is required.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
