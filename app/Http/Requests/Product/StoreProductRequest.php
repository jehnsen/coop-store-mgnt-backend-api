<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'retail_price' => ['required', 'integer', 'min:0', 'gte:cost_price'],
            'wholesale_price' => ['nullable', 'integer', 'min:0', 'gte:cost_price'],
            'contractor_price' => ['nullable', 'integer', 'min:0', 'gte:cost_price'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'material' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_qty' => ['nullable', 'numeric', 'min:0.01'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'is_vat_exempt' => ['nullable', 'boolean'],
            'track_inventory' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
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
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU already exists in the system.',
            'barcode.unique' => 'This barcode already exists in the system.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'unit_id.required' => 'Unit of measure is required.',
            'unit_id.exists' => 'Selected unit of measure does not exist.',
            'cost_price.required' => 'Cost price is required.',
            'cost_price.integer' => 'Cost price must be a valid amount.',
            'cost_price.min' => 'Cost price cannot be negative.',
            'retail_price.required' => 'Retail price is required.',
            'retail_price.integer' => 'Retail price must be a valid amount.',
            'retail_price.min' => 'Retail price cannot be negative.',
            'retail_price.gte' => 'Retail price must be greater than or equal to cost price.',
            'wholesale_price.integer' => 'Wholesale price must be a valid amount.',
            'wholesale_price.gte' => 'Wholesale price must be greater than or equal to cost price.',
            'contractor_price.integer' => 'Contractor price must be a valid amount.',
            'contractor_price.gte' => 'Contractor price must be greater than or equal to cost price.',
            'brand.max' => 'Brand cannot exceed 100 characters.',
            'size.max' => 'Size cannot exceed 50 characters.',
            'material.max' => 'Material cannot exceed 100 characters.',
            'color.max' => 'Color cannot exceed 50 characters.',
            'current_stock.numeric' => 'Current stock must be a number.',
            'current_stock.min' => 'Current stock cannot be negative.',
            'reorder_point.numeric' => 'Reorder point must be a number.',
            'reorder_point.min' => 'Reorder point cannot be negative.',
            'minimum_order_qty.numeric' => 'Minimum order quantity must be a number.',
            'minimum_order_qty.min' => 'Minimum order quantity must be at least 0.01.',
            'image.image' => 'File must be an image.',
            'image.mimes' => 'Image must be a JPEG, JPG, PNG, GIF, or WEBP file.',
            'image.max' => 'Image size cannot exceed 2MB.',
            'is_active.boolean' => 'Active status must be true or false.',
            'is_vat_exempt.boolean' => 'VAT exempt status must be true or false.',
            'track_inventory.boolean' => 'Track inventory must be true or false.',
            'allow_negative_stock.boolean' => 'Allow negative stock must be true or false.',
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
            'is_vat_exempt' => $this->boolean('is_vat_exempt', false),
            'track_inventory' => $this->boolean('track_inventory', true),
            'allow_negative_stock' => $this->boolean('allow_negative_stock', false),
        ]);
    }
}
