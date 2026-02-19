<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'brand' => $this->brand,
            'size' => $this->size,
            'material' => $this->material,
            'color' => $this->color,

            // Prices in pesos (converted from centavos)
            'cost_price' => $this->cost_price / 100,
            'retail_price' => $this->retail_price / 100,
            'wholesale_price' => $this->wholesale_price ? $this->wholesale_price / 100 : null,
            'contractor_price' => $this->contractor_price ? $this->contractor_price / 100 : null,

            // Stock information
            'current_stock' => $this->current_stock,
            'reorder_point' => $this->reorder_point,
            'minimum_order_qty' => $this->minimum_order_qty,
            'low_stock' => $this->reorder_point && $this->current_stock <= $this->reorder_point,

            // Image
            'image_url' => $this->image_path ? Storage::url($this->image_path) : null,

            // Flags
            'is_active' => (bool) $this->is_active,
            'is_vat_exempt' => (bool) $this->is_vat_exempt,
            'track_inventory' => (bool) $this->track_inventory,
            'allow_negative_stock' => (bool) $this->allow_negative_stock,

            // Relationships
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'uuid' => $this->category->uuid,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name,
                    'abbreviation' => $this->unit->abbreviation,
                ];
            }),

            'stock_by_branch' => $this->whenLoaded('stockByBranch', function () {
                return $this->stockByBranch->map(function ($stock) {
                    return [
                        'branch_id' => $stock->branch_id,
                        'branch_name' => $stock->branch->name ?? null,
                        'current_stock' => $stock->current_stock,
                        'reorder_point' => $stock->reorder_point,
                        'low_stock' => $stock->reorder_point && $stock->current_stock <= $stock->reorder_point,
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
