<?php

namespace App\Http\Requests\Savings;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceTimeDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'                  => ['required', 'string', 'exists:customers,uuid'],
            'principal_amount'               => ['required', 'numeric', 'min:0.01'],
            'interest_rate'                  => ['required', 'numeric', 'min:0.000001', 'max:1'],
            'term_months'                    => ['required', 'integer', 'min:1', 'max:360'],
            'placement_date'                 => ['nullable', 'date', 'before_or_equal:today'],
            'interest_method'                => ['nullable', Rule::in(['simple_on_maturity', 'periodic'])],
            'payment_frequency'              => ['nullable', Rule::in(['monthly', 'quarterly', 'semi_annual', 'on_maturity'])],
            'early_withdrawal_penalty_rate'  => ['nullable', 'numeric', 'min:0', 'max:1'],
            'payment_method'                 => ['nullable', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'internal_transfer',
            ])],
            'reference_number'               => ['nullable', 'string', 'max:100'],
            'notes'                          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $customer = Customer::where('uuid', $this->input('customer_uuid'))
                ->where('store_id', Auth::user()->store_id)
                ->first();

            if (! $customer) {
                $v->errors()->add('customer_uuid', 'Customer not found in this store.');
                return;
            }

            if (! $customer->is_member) {
                $v->errors()->add('customer_uuid', 'Only cooperative members can place a time deposit.');
            }
        });
    }
}
