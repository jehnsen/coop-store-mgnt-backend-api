<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate quantities
        $quantityOrdered = $this->quantity_ordered;
        $quantityReceived = $this->quantity_received;
        $quantityRemaining = $quantityOrdered - $quantityReceived;

        // Determine item status
        $status = 'pending';
        if ($quantityReceived > 0 && $quantityReceived < $quantityOrdered) {
            $status = 'partial';
        } elseif ($quantityReceived >= $quantityOrdered) {
            $status = 'received';
        }

        return [
            'id' => $this->id,

            // Product information
            'product' => $this->whenLoaded('product', function () {
                return [
                    'uuid' => $this->product->uuid,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'unit' => [
                        'name' => $this->product->unit->name ?? null,
                        'abbreviation' => $this->product->unit->abbreviation ?? null,
                    ],
                ];
            }, function () {
                // Fallback to stored product info if product not loaded
                return [
                    'name' => $this->product_name,
                    'sku' => $this->product_sku,
                ];
            }),

            // Quantities
            'quantity_ordered' => $quantityOrdered,
            'quantity_received' => $quantityReceived,
            'quantity_remaining' => $quantityRemaining,

            // Pricing - already converted to pesos by accessor
            'unit_cost' => $this->unit_price, // Note: DB field is unit_price
            'line_total' => $this->line_total,

            // Status
            'status' => $status,

            // Notes
            'notes' => $this->notes,
        ];
    }
}
