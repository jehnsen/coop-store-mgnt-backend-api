<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'payment_number'    => $this->payment_number,
            'amount'            => number_format($this->getRawOriginal('amount') / 100, 2, '.', ''),
            'principal_portion' => number_format($this->getRawOriginal('principal_portion') / 100, 2, '.', ''),
            'interest_portion'  => number_format($this->getRawOriginal('interest_portion') / 100, 2, '.', ''),
            'penalty_portion'   => number_format($this->getRawOriginal('penalty_portion') / 100, 2, '.', ''),
            'balance_before'    => number_format($this->getRawOriginal('balance_before') / 100, 2, '.', ''),
            'balance_after'     => number_format($this->getRawOriginal('balance_after') / 100, 2, '.', ''),
            'payment_method'    => $this->payment_method,
            'reference_number'  => $this->reference_number,
            'payment_date'      => $this->payment_date?->toDateString(),
            'notes'             => $this->notes,
            'is_reversed'       => (bool) $this->is_reversed,
            'reversed_at'       => $this->reversed_at?->toISOString(),
            'collected_by'      => $this->whenLoaded('user', fn () => $this->user->name ?? null),
            'reversed_by_name'  => $this->whenLoaded('reversedBy', fn () => $this->reversedBy?->name),
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
