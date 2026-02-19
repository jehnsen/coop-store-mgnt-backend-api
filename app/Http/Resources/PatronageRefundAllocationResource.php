<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatronageRefundAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'customer'              => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer?->uuid,
                'name'      => $this->customer?->name,
                'member_id' => $this->customer?->member_id,
            ]),
            'member_purchases'      => number_format($this->getRawOriginal('member_purchases') / 100, 2, '.', ''),
            'allocation_percentage' => $this->allocation_percentage,
            'allocation_amount'     => number_format($this->getRawOriginal('allocation_amount') / 100, 2, '.', ''),
            'status'                => $this->status,
            'payment_method'        => $this->payment_method,
            'reference_number'      => $this->reference_number,
            'paid_date'             => $this->paid_date?->toDateString(),
            'paid_by'               => $this->whenLoaded('paidBy', fn () => $this->paidBy?->name),
            'notes'                 => $this->notes,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
