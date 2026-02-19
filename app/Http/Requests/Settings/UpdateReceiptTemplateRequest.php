<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceiptTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'owner' || $this->user()->role === 'manager';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'header_text' => 'nullable|string|max:500',
            'footer_text' => 'nullable|string|max:500',
            'show_logo' => 'boolean',
            'paper_width' => 'nullable|integer|in:58,80',
            'show_bir_info' => 'boolean',
            'show_cashier' => 'boolean',
            'show_customer' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'header_text.max' => 'Header text cannot exceed 500 characters',
            'footer_text.max' => 'Footer text cannot exceed 500 characters',
            'paper_width.in' => 'Paper width must be either 58mm or 80mm',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('show_logo')) {
            $mergeData['show_logo'] = filter_var($this->show_logo, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('show_bir_info')) {
            $mergeData['show_bir_info'] = filter_var($this->show_bir_info, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('show_cashier')) {
            $mergeData['show_cashier'] = filter_var($this->show_cashier, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('show_customer')) {
            $mergeData['show_customer'] = filter_var($this->show_customer, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }
}
