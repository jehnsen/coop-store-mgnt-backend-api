<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableAgingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Supplier basic info
            'supplier' => [
                'uuid' => $this->uuid,
                'code' => $this->code,
                'name' => $this->name,
                'company_name' => $this->company_name,
                'phone' => $this->phone,
                'email' => $this->email,
                'payment_terms_days' => $this->payment_terms_days,
                'total_outstanding' => $this->total_outstanding,
            ],

            // Aging buckets (all amounts in pesos)
            'aging' => [
                'current' => $this->aging_current ?? 0,
                'days_31_60' => $this->aging_31_60 ?? 0,
                'days_61_90' => $this->aging_61_90 ?? 0,
                'days_over_90' => $this->aging_over_90 ?? 0,
            ],

            // Summary
            'total_outstanding' => $this->total_outstanding,

            // Additional metrics
            'oldest_invoice_days' => $this->oldest_invoice_days ?? 0,
        ];
    }
}
