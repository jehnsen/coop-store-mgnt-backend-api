<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'alternate_phone' => $this->mobile ?? $this->alternate_phone ?? null,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'tin' => $this->tin,
            'payment_terms_days' => $this->payment_terms_days,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,

            // AP (Accounts Payable) fields
            'total_outstanding' => $this->total_outstanding ?? 0,
            'total_purchases' => $this->total_purchases ?? 0,
            'payment_rating' => $this->payment_rating ?? 'good',

            // Statistics
            'total_purchase_orders' => $this->whenCounted('purchaseOrders'),
            'payables_count' => $this->whenCounted('payableTransactions'),
            'total_purchases_amount' => $this->when(isset($this->total_purchases_amount), function () {
                return $this->total_purchases_amount / 100; // Convert centavos to pesos
            }),
            'last_purchase_date' => $this->when(isset($this->last_purchase_date), function () {
                return $this->last_purchase_date;
            }),

            // Relationships
            'products_count' => $this->whenCounted('products'),

            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'uuid' => $product->uuid,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'supplier_price' => $product->pivot->supplier_price ? $product->pivot->supplier_price / 100 : null,
                        'lead_time_days' => $product->pivot->lead_time_days,
                        'is_preferred' => (bool) $product->pivot->is_preferred,
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
