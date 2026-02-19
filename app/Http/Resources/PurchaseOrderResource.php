<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
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
            'po_number' => $this->po_number,
            'status' => $this->status,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'total_amount' => $this->total_amount, // Already converted to pesos by accessor
            'notes' => $this->notes,

            // Supplier - basic fields only to avoid circular loading
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'uuid' => $this->supplier->uuid,
                    'name' => $this->supplier->name,
                    'contact_person' => $this->supplier->contact_person,
                    'phone' => $this->supplier->phone,
                    'email' => $this->supplier->email,
                    'payment_terms_days' => $this->supplier->payment_terms_days,
                ];
            }),

            // Items
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('purchaseOrderItems')),

            // User (creator) - basic fields
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // Branch
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
