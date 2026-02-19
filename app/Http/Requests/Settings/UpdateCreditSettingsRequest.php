<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditSettingsRequest extends FormRequest
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
            'default_credit_limit' => 'nullable|integer|min:0',
            'default_terms_days' => 'nullable|integer|min:1|max:365',
            'reminder_days_before' => 'nullable|integer|min:0|max:30',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'default_credit_limit.integer' => 'Credit limit must be a whole number',
            'default_credit_limit.min' => 'Credit limit cannot be negative',
            'default_terms_days.integer' => 'Terms days must be a whole number',
            'default_terms_days.min' => 'Terms days must be at least 1 day',
            'default_terms_days.max' => 'Terms days cannot exceed 365 days',
            'reminder_days_before.integer' => 'Reminder days must be a whole number',
            'reminder_days_before.min' => 'Reminder days cannot be negative',
            'reminder_days_before.max' => 'Reminder days cannot exceed 30 days',
        ];
    }
}
