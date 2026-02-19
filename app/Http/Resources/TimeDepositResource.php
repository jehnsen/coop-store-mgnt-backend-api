<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeDepositResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $principalCentavos       = $this->getRawOriginal('principal_amount');
        $currentBalanceCentavos  = $this->getRawOriginal('current_balance');
        $totalInterestCentavos   = $this->getRawOriginal('total_interest_earned');
        $expectedInterestCentavos = $this->getRawOriginal('expected_interest');

        $maturityDate  = $this->maturity_date;
        $daysToMaturity = $maturityDate
            ? (int) now()->startOfDay()->diffInDays($maturityDate, false)
            : null;
        $isMatured = $this->status === 'matured'
            || ($this->status === 'active' && $maturityDate && $maturityDate->lte(now()));

        return [
            'uuid'                           => $this->uuid,
            'account_number'                 => $this->account_number,
            'principal_amount'               => number_format($principalCentavos / 100, 2, '.', ''),
            'interest_rate'                  => (float) $this->interest_rate,
            'interest_rate_pct'              => round((float) $this->interest_rate * 100, 4),
            'interest_method'                => $this->interest_method,
            'payment_frequency'              => $this->payment_frequency,
            'term_months'                    => $this->term_months,
            'early_withdrawal_penalty_rate'  => (float) $this->early_withdrawal_penalty_rate,
            'early_withdrawal_penalty_pct'   => round((float) $this->early_withdrawal_penalty_rate * 100, 2),
            'placement_date'                 => $this->placement_date?->toDateString(),
            'maturity_date'                  => $maturityDate?->toDateString(),
            'current_balance'                => number_format($currentBalanceCentavos / 100, 2, '.', ''),
            'total_interest_earned'          => number_format($totalInterestCentavos / 100, 2, '.', ''),
            'expected_interest'              => number_format($expectedInterestCentavos / 100, 2, '.', ''),
            'days_to_maturity'               => $daysToMaturity,
            'is_matured'                     => $isMatured,
            'status'                         => $this->status,
            'matured_at'                     => $this->matured_at?->toISOString(),
            'pre_terminated_at'              => $this->pre_terminated_at?->toISOString(),
            'pre_termination_reason'         => $this->pre_termination_reason,
            'rollover_count'                 => $this->rollover_count,
            'parent_time_deposit_id'         => $this->parent_time_deposit_id,
            'notes'                          => $this->notes,
            'customer'                       => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer->uuid,
                'name'      => $this->customer->name,
                'member_id' => $this->customer->member_id,
            ]),
            'transactions'                   => TimeDepositTransactionResource::collection(
                $this->whenLoaded('transactions')
            ),
            'created_at'                     => $this->created_at?->toISOString(),
        ];
    }
}
