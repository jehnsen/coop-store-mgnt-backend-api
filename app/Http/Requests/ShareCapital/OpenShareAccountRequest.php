<?php

namespace App\Http\Requests\ShareCapital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenShareAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'       => [
                'required', 'string',
                Rule::exists('customers', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_member', true)
                      ->where('is_active', true)
                ),
            ],
            'share_type'          => ['required', 'string', Rule::in(['regular', 'preferred'])],
            'subscribed_shares'   => ['required', 'integer', 'min:1'],
            'par_value_per_share' => ['required', 'numeric', 'min:1'], // in pesos
            'opened_date'         => ['nullable', 'date', 'before_or_equal:today'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_uuid.exists' => 'Customer not found, is not an active member, or does not belong to your store.',
            'subscribed_shares.min' => 'A member must subscribe to at least 1 share.',
            'par_value_per_share.min' => 'Par value must be at least ₱1.00.',
        ];
    }
}
