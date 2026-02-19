<?php

namespace App\Http\Requests\Loan;

use App\Models\Loan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordLoanPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'], // pesos
            'payment_method'   => ['required', 'string', Rule::in(['cash', 'gcash', 'maya', 'bank_transfer', 'check', 'salary_deduction'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date'     => ['nullable', 'date', 'before_or_equal:today'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $loan = Loan::where('uuid', $this->route('uuid'))
                ->where('store_id', auth()->user()->store_id)
                ->first();

            if (! $loan) {
                return;
            }

            $amountCentavos     = (int) round($this->input('amount') * 100);
            $outstandingCentavos = $loan->getRawOriginal('outstanding_balance');
            $penaltiesOutstanding = $loan->getRawOriginal('total_penalties_outstanding');
            $maxAcceptable      = $outstandingCentavos + $penaltiesOutstanding;

            if ($amountCentavos > $maxAcceptable) {
                $v->errors()->add('amount', sprintf(
                    'Payment of ₱%s exceeds the total outstanding (balance ₱%s + penalties ₱%s = ₱%s).',
                    number_format($this->input('amount'), 2),
                    number_format($outstandingCentavos / 100, 2),
                    number_format($penaltiesOutstanding / 100, 2),
                    number_format($maxAcceptable / 100, 2),
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be at least ₱0.01.',
        ];
    }
}
