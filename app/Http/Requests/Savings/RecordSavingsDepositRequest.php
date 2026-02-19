<?php

namespace App\Http\Requests\Savings;

use App\Models\MemberSavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordSavingsDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'transaction_type' => ['nullable', Rule::in(['deposit', 'compulsory_deduction', 'adjustment'])],
            'payment_method'   => ['nullable', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check',
                'salary_deduction', 'internal_transfer',
            ])],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'transaction_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $account = MemberSavingsAccount::where('uuid', $this->route('uuid'))->first();

            if (! $account) {
                return;
            }

            if ($account->status !== 'active') {
                $v->errors()->add('amount', 'Deposits can only be made to active savings accounts.');
            }
        });
    }
}
