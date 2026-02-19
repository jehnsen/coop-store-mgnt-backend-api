<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: record admission fee at the time of approval
            'payment_method'        => ['nullable', 'in:cash,check,bank_transfer,gcash,maya,internal_transfer'],
            'admission_fee_amount'  => ['nullable', 'numeric', 'min:0.01'],
            'reference_number'      => ['nullable', 'string', 'max:100'],
            'transaction_date'      => ['nullable', 'date', 'before_or_equal:today'],
            'fee_notes'             => ['nullable', 'string', 'max:1000'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ];
    }
}
