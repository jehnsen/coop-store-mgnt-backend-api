<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipFeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'fee_number'       => $this->fee_number,
            'fee_type'         => $this->fee_type,
            'amount'           => number_format($this->getRawOriginal('amount') / 100, 2, '.', ''),
            'payment_method'   => $this->payment_method,
            'reference_number' => $this->reference_number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'period_year'      => $this->period_year,
            'notes'            => $this->notes,
            'is_reversed'      => (bool) $this->is_reversed,
            'reversed_at'      => $this->reversed_at?->toISOString(),
            'reversed_by'      => $this->whenLoaded('reversedBy', fn () => $this->reversedBy?->name),
            'processed_by'     => $this->whenLoaded('user', fn () => $this->user?->name),
            'customer'         => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer?->uuid,
                'name'      => $this->customer?->name,
                'member_id' => $this->customer?->member_id,
            ]),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
