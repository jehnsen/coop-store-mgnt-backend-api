<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic information
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'tin' => $this->tin,
            'business_name' => $this->company_name, // Map to company_name from DB
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,

            // Credit summary (converted from centavos to pesos)
            'credit_limit' => $this->credit_limit,
            'credit_terms_days' => $this->credit_terms_days,
            'total_outstanding' => $this->total_outstanding,
            'available_credit' => max(0, $this->credit_limit - $this->total_outstanding),
            'total_purchases' => $this->total_purchases,
            'payment_rating' => $this->payment_rating,

            // Computed fields
            'last_purchase_date' => $this->sales()
                ->latest('sale_date')
                ->value('sale_date'),

            'customer_tier' => $this->getCustomerTier(),

            // Relationships
            'sales_count' => $this->whenLoaded('sales', function () {
                return $this->sales->count();
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Calculate customer tier based on total purchases.
     *
     * @return string
     */
    private function getCustomerTier(): string
    {
        $totalPurchasesInPesos = $this->total_purchases;

        if ($totalPurchasesInPesos >= 1000001) {
            return 'Platinum';
        } elseif ($totalPurchasesInPesos >= 500001) {
            return 'Gold';
        } elseif ($totalPurchasesInPesos >= 100001) {
            return 'Silver';
        }

        return 'Bronze';
    }
}
