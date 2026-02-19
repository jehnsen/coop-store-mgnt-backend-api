<?php

namespace App\Http\Requests\Savings;

use App\Models\TimeDeposit;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreTerminateTimeDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pre_termination_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method'       => ['nullable', Rule::in([
                'cash', 'gcash', 'maya', 'bank_transfer', 'check', 'internal_transfer',
            ])],
            'reference_number'     => ['nullable', 'string', 'max:100'],
            'reason'               => ['required', 'string', 'min:5', 'max:1000'],
            'notes'                => ['nullable', 'string', 'max:1000'],
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
                $v->errors()->add('pre_termination_date', 'Only active time deposits can be pre-terminated.');
                return;
            }

            $terminationDate = Carbon::parse($this->input('pre_termination_date'));
            $placementDate   = Carbon::parse($td->placement_date);

            if ($terminationDate->lt($placementDate)) {
                $v->errors()->add('pre_termination_date', 'Termination date cannot be before placement date.');
            }

            if ($terminationDate->gte(Carbon::parse($td->maturity_date))) {
                $v->errors()->add('pre_termination_date', 'Use the mature endpoint — this date is on or after maturity.');
            }
        });
    }
}
