<?php

namespace App\Http\Requests\Loan;

use App\Models\LoanProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid'      => [
                'required', 'string',
                Rule::exists('customers', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_member', true)
                      ->where('is_active', true)
                ),
            ],
            'loan_product_uuid'  => [
                'required', 'string',
                Rule::exists('loan_products', 'uuid')->where(fn ($q) =>
                    $q->where('store_id', auth()->user()->store_id)
                      ->where('is_active', true)
                ),
            ],
            'principal_amount'   => ['required', 'numeric', 'min:0.01'], // pesos
            'term_months'        => ['required', 'integer', 'min:1'],
            'payment_interval'   => ['nullable', 'string', Rule::in(['weekly', 'semi_monthly', 'monthly'])],
            'purpose'            => ['required', 'string', 'max:1000'],
            'collateral_description' => ['nullable', 'string', 'max:1000'],
            'application_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'first_payment_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $loanProduct = LoanProduct::where('uuid', $this->input('loan_product_uuid'))
                ->where('store_id', auth()->user()->store_id)
                ->first();

            if (! $loanProduct) {
                return;
            }

            $principalCentavos = (int) round($this->input('principal_amount') * 100);
            $minCentavos       = $loanProduct->getRawOriginal('min_amount');
            $maxCentavos       = $loanProduct->getRawOriginal('max_amount');

            if ($principalCentavos < $minCentavos) {
                $v->errors()->add('principal_amount', sprintf(
                    'Minimum loanable amount for this product is ₱%s.',
                    number_format($minCentavos / 100, 2),
                ));
            }

            if ($principalCentavos > $maxCentavos) {
                $v->errors()->add('principal_amount', sprintf(
                    'Maximum loanable amount for this product is ₱%s.',
                    number_format($maxCentavos / 100, 2),
                ));
            }

            $termMonths = (int) $this->input('term_months');
            if ($termMonths > $loanProduct->max_term_months) {
                $v->errors()->add('term_months', sprintf(
                    'Maximum term for this product is %d months.',
                    $loanProduct->max_term_months,
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_uuid.exists'     => 'Customer not found, is not an active member, or does not belong to your store.',
            'loan_product_uuid.exists' => 'Loan product not found or is not active.',
            'principal_amount.min'     => 'Principal amount must be greater than zero.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_interval' => $this->input('payment_interval', 'monthly'),
        ]);
    }
}
