<?php

namespace App\Http\Requests\Savings;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpenSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'   => ['required', 'string', 'exists:customers,uuid'],
            'savings_type'    => ['nullable', Rule::in(['voluntary', 'compulsory'])],
            'interest_rate'   => ['nullable', 'numeric', 'min:0', 'max:1'],
            'minimum_balance' => ['nullable', 'numeric', 'min:0'],
            'opened_date'     => ['nullable', 'date', 'before_or_equal:today'],
            'notes'           => ['nullable', 'string', 'max:1000'],
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
                $v->errors()->add('customer_uuid', 'Only cooperative members can open a savings account.');
            }
        });
    }
}
