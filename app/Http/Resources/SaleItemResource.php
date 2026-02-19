<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
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

            'product' => [
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'unit_of_measure' => $this->product->unit_of_measure,
            ],

            'quantity' => (float) $this->quantity,
            'unit_price' => number_format($this->unit_price / 100, 2, '.', ''),

            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => number_format($this->discount_amount / 100, 2, '.', ''),

            'line_total' => number_format($this->line_total / 100, 2, '.', ''),

            // Include if this is a refund item
            'is_refund' => $this->quantity < 0,
            'parent_sale_item_id' => $this->parent_sale_item_id,
        ];
    }
}
