<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($product) {
                return [
                    'uuid' => $product->uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'brand' => $product->brand,

                    // Prices in pesos
                    'retail_price' => $product->retail_price / 100,
                    'wholesale_price' => $product->wholesale_price ? $product->wholesale_price / 100 : null,

                    // Stock information
                    'current_stock' => $product->current_stock,
                    'low_stock' => $product->reorder_point && $product->current_stock <= $product->reorder_point,

                    // Image thumbnail
                    'image_url' => $product->image_path ? \Storage::url($product->image_path) : null,

                    // Flags
                    'is_active' => (bool) $product->is_active,

                    // Category (if loaded)
                    'category' => $product->relationLoaded('category') ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,

                    // Unit (if loaded)
                    'unit' => $product->relationLoaded('unit') ? [
                        'id' => $product->unit->id,
                        'abbreviation' => $product->unit->abbreviation,
                    ] : null,
                ];
            }),
        ];
    }
}
