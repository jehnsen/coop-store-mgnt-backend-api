<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:walk_in,regular,contractor,government'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'tin' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'credit_terms_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
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
            'name.required' => 'Customer name is required.',
            'name.max' => 'Customer name cannot exceed 255 characters.',
            'type.required' => 'Customer type is required.',
            'type.in' => 'Customer type must be one of: walk_in, regular, contractor, government.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'phone.required' => 'Phone number is required.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'alternate_phone.max' => 'Alternate phone cannot exceed 20 characters.',
            'city.max' => 'City cannot exceed 100 characters.',
            'province.max' => 'Province cannot exceed 100 characters.',
            'postal_code.max' => 'Postal code cannot exceed 10 characters.',
            'tin.max' => 'TIN cannot exceed 20 characters.',
            'business_name.max' => 'Business name cannot exceed 255 characters.',
            'credit_limit.integer' => 'Credit limit must be a valid amount in centavos.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
            'credit_terms_days.integer' => 'Credit terms days must be a number.',
            'credit_terms_days.min' => 'Credit terms must be at least 1 day.',
            'credit_terms_days.max' => 'Credit terms cannot exceed 365 days.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean strings to actual booleans if present
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
