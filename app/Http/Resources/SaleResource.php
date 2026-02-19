<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sale_number' => $this->sale_number,
            'sale_date' => $this->sale_date?->toDateTimeString(),
            'status' => $this->status,
            'price_tier' => $this->price_tier,

            // Monetary values converted to pesos (2 decimal places)
            'subtotal' => number_format($this->subtotal / 100, 2, '.', ''),
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => number_format($this->discount_amount / 100, 2, '.', ''),
            'vat_amount' => number_format($this->vat_amount / 100, 2, '.', ''),
            'total_amount' => number_format($this->total_amount / 100, 2, '.', ''),

            'notes' => $this->notes,

            // Void information
            'voided_at' => $this->voided_at?->toDateTimeString(),
            'void_reason' => $this->void_reason,

            // Timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Relationships
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'uuid' => $this->customer->uuid,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                    'customer_type' => $this->customer->customer_type,
                ];
            }),

            'items' => SaleItemResource::collection($this->whenLoaded('items')),

            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                ];
            }),

            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'uuid' => $this->branch->uuid,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code,
                ];
            }),

            'voided_by_user' => $this->whenLoaded('voidedBy', function () {
                return [
                    'uuid' => $this->voidedBy->uuid,
                    'name' => $this->voidedBy->name,
                ];
            }),
        ];
    }
}
