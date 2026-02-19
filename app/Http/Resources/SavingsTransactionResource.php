<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'transaction_number' => $this->transaction_number,
            'transaction_type'   => $this->transaction_type,
            'amount'             => number_format($this->getRawOriginal('amount') / 100, 2, '.', ''),
            'balance_before'     => number_format($this->getRawOriginal('balance_before') / 100, 2, '.', ''),
            'balance_after'      => number_format($this->getRawOriginal('balance_after') / 100, 2, '.', ''),
            'payment_method'     => $this->payment_method,
            'reference_number'   => $this->reference_number,
            'transaction_date'   => $this->transaction_date?->toDateString(),
            'notes'              => $this->notes,
            'is_reversed'        => (bool) $this->is_reversed,
            'reversed_at'        => $this->reversed_at?->toISOString(),
            'processed_by'       => $this->whenLoaded('user', fn () => $this->user?->name),
            'reversed_by_name'   => $this->whenLoaded('reversedBy', fn () => $this->reversedBy?->name),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
