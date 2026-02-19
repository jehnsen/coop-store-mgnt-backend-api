<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_item_id' => $this->sale_item_id,
            'quantity' => (float) $this->quantity,
            'status' => $this->status ?? 'preparing',
            'notes' => $this->notes,

            // Product information
            'product' => $this->whenLoaded('product', function () {
                return [
                    'uuid' => $this->product->uuid,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'description' => $this->product->description,
                ];
            }),

            // Unit information
            'unit' => $this->whenLoaded('product', function () {
                return $this->product->unit ? [
                    'name' => $this->product->unit->name,
                    'abbreviation' => $this->product->unit->abbreviation,
                ] : null;
            }),

            // Sale item information
            'sale_item' => $this->whenLoaded('saleItem', function () {
                return [
                    'id' => $this->saleItem->id,
                    'product_name' => $this->saleItem->product_name,
                    'product_sku' => $this->saleItem->product_sku,
                    'quantity' => (float) $this->saleItem->quantity,
                    'unit_price' => (float) $this->saleItem->unit_price,
                    'line_total' => (float) $this->saleItem->line_total,
                ];
            }),
        ];
    }
}
