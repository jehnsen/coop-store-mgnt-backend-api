<?php

namespace App\Http\Requests\Loan;

use App\Models\LoanPenalty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WaivePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'waived_amount' => ['required', 'numeric', 'min:0.01'], // pesos
            'reason'        => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $penalty = LoanPenalty::where('uuid', $this->route('penUuid'))
                ->where('store_id', auth()->user()->store_id)
                ->first();

            if (! $penalty) {
                return;
            }

            if ($penalty->is_paid) {
                $v->errors()->add('waived_amount', 'Cannot waive a penalty that has already been paid.');
                return;
            }

            $waivedCentavos  = (int) round($this->input('waived_amount') * 100);
            $netPenaltyCentavos = $penalty->getRawOriginal('net_penalty');

            if ($waivedCentavos > $netPenaltyCentavos) {
                $v->errors()->add('waived_amount', sprintf(
                    'Waived amount (₱%s) cannot exceed the net penalty (₱%s).',
                    number_format($this->input('waived_amount'), 2),
                    number_format($netPenaltyCentavos / 100, 2),
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'waived_amount.min' => 'Waived amount must be at least ₱0.01.',
            'reason.min'        => 'Please provide a reason for the waiver (at least 5 characters).',
        ];
    }
}
