<?php

namespace App\Http\Requests\ShareCapital;

use App\Models\MemberShareAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IssueShareCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shares_covered' => ['required', 'integer', 'min:1'],
            'issue_date'     => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $account = MemberShareAccount::where('uuid', $this->route('uuid'))->first();

            if (! $account) {
                return;
            }

            $sharesCovered    = (int) $this->input('shares_covered');
            $parValueCentavos = $account->getRawOriginal('par_value_per_share');
            $paidUpCentavos   = $account->getRawOriginal('total_paid_up_amount');
            $paidUpShares     = $parValueCentavos > 0 ? (int) floor($paidUpCentavos / $parValueCentavos) : 0;

            if ($sharesCovered > $paidUpShares) {
                $v->errors()->add('shares_covered', sprintf(
                    'Only %d shares are fully paid up. Cannot issue a certificate for %d shares.',
                    $paidUpShares, $sharesCovered,
                ));
            }
        });
    }

    public function messages(): array
    {
        return [
            'shares_covered.min' => 'Certificate must cover at least 1 share.',
        ];
    }
}
