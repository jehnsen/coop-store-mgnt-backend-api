<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                    => $this->uuid,
            'loan_number'             => $this->loan_number,
            'status'                  => $this->status,
            'payment_interval'        => $this->payment_interval,
            'interest_method'         => $this->interest_method,
            'interest_rate'           => (float) $this->interest_rate,
            'interest_rate_pct'       => round((float) $this->interest_rate * 100, 4),
            'term_months'             => $this->term_months,
            'purpose'                 => $this->purpose,
            'collateral_description'  => $this->collateral_description,
            'rejection_reason'        => $this->rejection_reason,

            // Monetary (pesos, 2 decimal)
            'principal_amount'        => number_format($this->getRawOriginal('principal_amount') / 100, 2, '.', ''),
            'processing_fee'          => number_format($this->getRawOriginal('processing_fee') / 100, 2, '.', ''),
            'service_fee'             => number_format($this->getRawOriginal('service_fee') / 100, 2, '.', ''),
            'net_proceeds'            => number_format($this->getRawOriginal('net_proceeds') / 100, 2, '.', ''),
            'total_interest'          => number_format($this->getRawOriginal('total_interest') / 100, 2, '.', ''),
            'total_payable'           => number_format($this->getRawOriginal('total_payable') / 100, 2, '.', ''),
            'amortization_amount'     => number_format($this->getRawOriginal('amortization_amount') / 100, 2, '.', ''),
            'outstanding_balance'     => number_format($this->getRawOriginal('outstanding_balance') / 100, 2, '.', ''),
            'total_principal_paid'    => number_format($this->getRawOriginal('total_principal_paid') / 100, 2, '.', ''),
            'total_interest_paid'     => number_format($this->getRawOriginal('total_interest_paid') / 100, 2, '.', ''),
            'total_penalty_paid'      => number_format($this->getRawOriginal('total_penalty_paid') / 100, 2, '.', ''),
            'total_penalties_outstanding' => number_format($this->getRawOriginal('total_penalties_outstanding') / 100, 2, '.', ''),

            // Dates
            'application_date'        => $this->application_date?->toDateString(),
            'approval_date'           => $this->approval_date?->toDateString(),
            'disbursement_date'       => $this->disbursement_date?->toDateString(),
            'first_payment_date'      => $this->first_payment_date?->toDateString(),
            'maturity_date'           => $this->maturity_date?->toDateString(),

            // Relations
            'customer'                => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer->uuid,
                'name'      => $this->customer->name,
                'member_id' => $this->customer->member_id,
            ]),
            'loan_product'            => $this->whenLoaded('loanProduct', fn () => [
                'uuid'      => $this->loanProduct->uuid,
                'name'      => $this->loanProduct->name,
                'loan_type' => $this->loanProduct->loan_type,
            ]),
            'officer'                 => $this->whenLoaded('officer', fn () => $this->officer?->name),
            'approved_by_name'        => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'disbursed_by_name'       => $this->whenLoaded('disbursedBy', fn () => $this->disbursedBy?->name),
            'schedule'                => LoanAmortizationScheduleResource::collection($this->whenLoaded('amortizationSchedules')),
            'payments'                => LoanPaymentResource::collection($this->whenLoaded('payments')),
            'penalties'               => LoanPenaltyResource::collection($this->whenLoaded('penalties')),

            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
