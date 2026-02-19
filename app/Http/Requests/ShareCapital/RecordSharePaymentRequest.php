<?php

namespace App\Http\Requests\ShareCapital;

use App\Models\MemberShareAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordSharePaymentRequest extends FormRequest
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
            $account = $this->route('account'); // MemberShareAccount resolved by route model binding

            if (! $account instanceof MemberShareAccount) {
                $account = MemberShareAccount::where('uuid', $this->route('uuid'))->first();
            }

            if (! $account) {
                return;
            }

            if ($account->status !== 'active') {
                $v->errors()->add('amount', 'Cannot record a payment on a non-active share account.');
                return;
            }

            $amountCentavos          = (int) round($this->input('amount') * 100);
            $currentPaidCentavos     = $account->getRawOriginal('total_paid_up_amount');
            $totalSubscribedCentavos = $account->getRawOriginal('total_subscribed_amount');
            $remainingCentavos       = $totalSubscribedCentavos - $currentPaidCentavos;

            if ($amountCentavos > $remainingCentavos) {
                $v->errors()->add('amount', sprintf(
                    'Payment of ₱%s exceeds the remaining subscription of ₱%s.',
                    number_format($this->input('amount'), 2),
                    number_format($remainingCentavos / 100, 2),
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
