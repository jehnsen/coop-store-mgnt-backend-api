<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRequest extends FormRequest
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
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'string', 'exists:products,uuid'],
            'updates' => ['required', 'array'],
            'updates.retail_price' => ['nullable', 'integer', 'min:0'],
            'updates.wholesale_price' => ['nullable', 'integer', 'min:0'],
            'updates.contractor_price' => ['nullable', 'integer', 'min:0'],
            'updates.cost_price' => ['nullable', 'integer', 'min:0'],
            'updates.current_stock' => ['nullable', 'numeric', 'min:0'],
            'updates.reorder_point' => ['nullable', 'numeric', 'min:0'],
            'updates.is_active' => ['nullable', 'boolean'],
            'updates.is_vat_exempt' => ['nullable', 'boolean'],
            'updates.category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
            'product_ids.required' => 'Product IDs are required.',
            'product_ids.array' => 'Product IDs must be an array.',
            'product_ids.min' => 'At least one product must be selected.',
            'product_ids.*.required' => 'Each product ID is required.',
            'product_ids.*.exists' => 'One or more selected products do not exist.',
            'updates.required' => 'Updates data is required.',
            'updates.array' => 'Updates must be an array.',
            'updates.retail_price.integer' => 'Retail price must be a valid amount.',
            'updates.retail_price.min' => 'Retail price cannot be negative.',
            'updates.wholesale_price.integer' => 'Wholesale price must be a valid amount.',
            'updates.wholesale_price.min' => 'Wholesale price cannot be negative.',
            'updates.contractor_price.integer' => 'Contractor price must be a valid amount.',
            'updates.contractor_price.min' => 'Contractor price cannot be negative.',
            'updates.cost_price.integer' => 'Cost price must be a valid amount.',
            'updates.cost_price.min' => 'Cost price cannot be negative.',
            'updates.current_stock.numeric' => 'Current stock must be a number.',
            'updates.current_stock.min' => 'Current stock cannot be negative.',
            'updates.reorder_point.numeric' => 'Reorder point must be a number.',
            'updates.reorder_point.min' => 'Reorder point cannot be negative.',
            'updates.is_active.boolean' => 'Active status must be true or false.',
            'updates.is_vat_exempt.boolean' => 'VAT exempt status must be true or false.',
            'updates.category_id.exists' => 'Selected category does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert boolean strings to actual booleans in updates
        $updates = $this->input('updates', []);

        if (isset($updates['is_active'])) {
            $updates['is_active'] = filter_var($updates['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($updates['is_vat_exempt'])) {
            $updates['is_vat_exempt'] = filter_var($updates['is_vat_exempt'], FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge(['updates' => $updates]);
    }
}
