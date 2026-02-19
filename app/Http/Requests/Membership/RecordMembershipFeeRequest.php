<?php

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

class RecordMembershipFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'    => ['required', 'string', 'exists:customers,uuid'],
            'fee_type'         => ['required', 'in:admission_fee,annual_dues,reinstatement_fee,other'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['nullable', 'in:cash,check,bank_transfer,gcash,maya,internal_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
            'period_year'      => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }
}
