<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DeliveryResource extends JsonResource
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
            'delivery_number' => $this->delivery_number,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'delivery_address' => $this->delivery_address,
            'delivery_city' => $this->delivery_city,
            'delivery_province' => $this->delivery_province,
            'delivery_postal_code' => $this->delivery_postal_code,
            'contact_person' => $this->contact_person,
            'contact_phone' => $this->contact_phone,
            'delivery_instructions' => $this->delivery_instructions,
            'dispatched_at' => $this->dispatched_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'failed_at' => $this->failed_at?->format('Y-m-d H:i:s'),
            'failed_reason' => $this->failed_reason,
            'proof_of_delivery_path' => $this->proof_of_delivery_path,
            'proof_of_delivery_url' => $this->proof_of_delivery_path
                ? Storage::url($this->proof_of_delivery_path)
                : null,
            'signature_path' => $this->signature_path,
            'signature_url' => $this->signature_path
                ? Storage::url($this->signature_path)
                : null,
            'received_by' => $this->received_by,
            'notes' => $this->delivery_notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relationships
            'sale' => $this->whenLoaded('sale', function () {
                return [
                    'uuid' => $this->sale->uuid,
                    'sale_number' => $this->sale->sale_number,
                    'total_amount' => $this->sale->total_amount,
                    'payment_status' => $this->sale->payment_status,
                    'status' => $this->sale->status,
                    'sale_date' => $this->sale->sale_date?->format('Y-m-d H:i:s'),
                ];
            }),

            'customer' => $this->whenLoaded('customer', function () {
                return $this->customer ? [
                    'uuid' => $this->customer->uuid,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'email' => $this->customer->email,
                    'address' => $this->customer->address,
                ] : null;
            }),

            'driver' => $this->whenLoaded('assignedToUser', function () {
                return $this->assignedToUser ? [
                    'id' => $this->assignedToUser->id,
                    'name' => $this->assignedToUser->name,
                    'email' => $this->assignedToUser->email,
                    'phone' => $this->assignedToUser->phone,
                ] : null;
            }),

            'items' => DeliveryItemResource::collection($this->whenLoaded('deliveryItems')),
        ];
    }
}
