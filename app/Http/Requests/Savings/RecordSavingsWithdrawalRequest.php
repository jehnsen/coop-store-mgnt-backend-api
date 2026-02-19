<?php

namespace App\Http\Requests\Savings;

use App\Models\MemberSavingsAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordSavingsWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
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
                $v->errors()->add('amount', 'Withdrawals can only be made from active savings accounts.');
                return;
            }

            $amountCentavos    = (int) round($this->input('amount') * 100);
            $currentCentavos   = $account->getRawOriginal('current_balance');
            $minimumCentavos   = $account->getRawOriginal('minimum_balance');
            $availableCentavos = max(0, $currentCentavos - $minimumCentavos);

            if ($amountCentavos > $availableCentavos) {
                $v->errors()->add('amount', sprintf(
                    'Withdrawal of ₱%s exceeds the available balance of ₱%s (minimum maintaining balance: ₱%s).',
                    number_format($this->input('amount'), 2),
                    number_format($availableCentavos / 100, 2),
                    number_format($minimumCentavos / 100, 2),
                ));
            }
        });
    }
}
