<?php

namespace App\Http\Requests\Savings;

use App\Models\TimeDeposit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AccrueTimeDepositInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_from'      => ['required', 'date'],
            'period_to'        => ['required', 'date', 'after:period_from'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method'   => ['nullable', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'internal_transfer',
            ])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $td = TimeDeposit::where('uuid', $this->route('uuid'))->first();

            if (! $td) {
                return;
            }

            if ($td->status !== 'active') {
                $v->errors()->add('period_from', 'Interest can only be accrued for active time deposits.');
            }

            if ($td->interest_method === 'simple_on_maturity') {
                $v->errors()->add('period_from', 'This time deposit uses simple_on_maturity — accrue at maturity instead.');
            }
        });
    }
}
