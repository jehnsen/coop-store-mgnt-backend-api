<?php

namespace App\Http\Requests\Membership;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class SubmitMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'         => ['required', 'string', 'exists:customers,uuid'],
            'application_type'      => ['nullable', 'in:new,reinstatement'],
            'application_date'      => ['nullable', 'date', 'before_or_equal:today'],
            'civil_status'          => ['nullable', 'string', 'max:30'],
            'occupation'            => ['nullable', 'string', 'max:100'],
            'employer'              => ['nullable', 'string', 'max:150'],
            'monthly_income_range'  => ['nullable', 'string', 'max:50'],
            'beneficiary_info'      => ['nullable', 'string', 'max:1000'],
            'admission_fee_amount'  => ['nullable', 'numeric', 'min:0'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $uuid     = $this->input('customer_uuid');
            $customer = Customer::where('uuid', $uuid)
                ->where('store_id', Auth::user()->store_id)
                ->first();

            if (! $customer) {
                $v->errors()->add('customer_uuid', 'Customer not found in this store.');
                return;
            }

            // Cannot apply again if already a regular member
            if ($customer->member_status === 'regular') {
                $v->errors()->add('customer_uuid', 'This customer is already an active member.');
            }

            // Cannot apply if already an applicant
            if ($customer->member_status === 'applicant') {
                $v->errors()->add('customer_uuid', 'This customer already has a pending application.');
            }

            // Attach resolved customer_id for the service
            $this->merge(['customer_id' => $customer->id]);
        });
    }
}
