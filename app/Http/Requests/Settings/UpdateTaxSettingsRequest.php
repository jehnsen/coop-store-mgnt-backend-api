<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxSettingsRequest extends FormRequest
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
            'vat_rate' => 'nullable|numeric|min:0|max:100',
            'vat_inclusive' => 'boolean',
            'is_bmbe' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'vat_rate.numeric' => 'VAT rate must be a number',
            'vat_rate.min' => 'VAT rate cannot be negative',
            'vat_rate.max' => 'VAT rate cannot exceed 100%',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('vat_inclusive')) {
            $mergeData['vat_inclusive'] = filter_var($this->vat_inclusive, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('is_bmbe')) {
            $mergeData['is_bmbe'] = filter_var($this->is_bmbe, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }
}
