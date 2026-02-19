<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberSavingsAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentCentavos  = $this->getRawOriginal('current_balance');
        $minimumCentavos  = $this->getRawOriginal('minimum_balance');
        $availableCentavos = max(0, $currentCentavos - $minimumCentavos);

        return [
            'uuid'                    => $this->uuid,
            'account_number'          => $this->account_number,
            'savings_type'            => $this->savings_type,
            'interest_rate'           => (float) $this->interest_rate,
            'interest_rate_pct'       => round((float) $this->interest_rate * 100, 4),
            'current_balance'         => number_format($currentCentavos / 100, 2, '.', ''),
            'minimum_balance'         => number_format($minimumCentavos / 100, 2, '.', ''),
            'available_for_withdrawal' => number_format($availableCentavos / 100, 2, '.', ''),
            'total_deposited'         => number_format($this->getRawOriginal('total_deposited') / 100, 2, '.', ''),
            'total_withdrawn'         => number_format($this->getRawOriginal('total_withdrawn') / 100, 2, '.', ''),
            'total_interest_earned'   => number_format($this->getRawOriginal('total_interest_earned') / 100, 2, '.', ''),
            'status'                  => $this->status,
            'opened_date'             => $this->opened_date?->toDateString(),
            'closed_date'             => $this->closed_date?->toDateString(),
            'last_transaction_date'   => $this->last_transaction_date?->toDateString(),
            'notes'                   => $this->notes,
            'customer'                => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer->uuid,
                'name'      => $this->customer->name,
                'member_id' => $this->customer->member_id,
            ]),
            'transactions'            => SavingsTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at'              => $this->created_at?->toISOString(),
        ];
    }
}
