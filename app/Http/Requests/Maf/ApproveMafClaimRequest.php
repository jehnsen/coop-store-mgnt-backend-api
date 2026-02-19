<?php

declare(strict_types=1);

namespace App\Http\Requests\Maf;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMafClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:0.01'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $claim = $this->route('claim');

            if ($claim && !$validator->errors()->has('approved_amount')) {
                $approvedCentavos = (int) round((float) $this->input('approved_amount', 0) * 100);
                $benefitCentavos  = $claim->mafProgram
                    ? (int) $claim->mafProgram->getRawOriginal('benefit_amount')
                    : PHP_INT_MAX;

                if ($approvedCentavos > $benefitCentavos) {
                    $validator->errors()->add(
                        'approved_amount',
                        sprintf(
                            'Approved amount (₱%s) cannot exceed the program\'s maximum benefit of ₱%s.',
                            number_format($approvedCentavos / 100, 2),
                            number_format($benefitCentavos / 100, 2),
                        )
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'approved_amount.required' => 'Approved amount is required.',
            'approved_amount.min'      => 'Approved amount must be at least ₱0.01.',
        ];
    }

    public function attributes(): array
    {
        return [
            'approved_amount' => 'approved amount',
        ];
    }
}
