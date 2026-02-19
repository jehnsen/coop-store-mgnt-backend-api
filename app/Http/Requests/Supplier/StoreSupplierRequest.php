<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'tin' => ['nullable', 'string', 'max:20'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
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
            'name.required' => 'Supplier name is required.',
            'name.max' => 'Supplier name cannot exceed 255 characters.',
            'contact_person.max' => 'Contact person name cannot exceed 255 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'phone.required' => 'Phone number is required.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'alternate_phone.max' => 'Alternate phone cannot exceed 20 characters.',
            'city.max' => 'City cannot exceed 100 characters.',
            'province.max' => 'Province cannot exceed 100 characters.',
            'postal_code.max' => 'Postal code cannot exceed 10 characters.',
            'tin.max' => 'TIN cannot exceed 20 characters.',
            'payment_terms_days.integer' => 'Payment terms must be a number.',
            'payment_terms_days.min' => 'Payment terms cannot be negative.',
            'payment_terms_days.max' => 'Payment terms cannot exceed 365 days.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean strings to actual booleans
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
