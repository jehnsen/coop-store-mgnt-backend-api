<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditAgingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Customer basic info
            'customer' => [
                'uuid' => $this->uuid,
                'code' => $this->code,
                'name' => $this->name,
                'type' => $this->type,
                'phone' => $this->phone,
                'email' => $this->email,
                'credit_limit' => $this->credit_limit,
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
            'credit_utilization' => $this->credit_limit > 0
                ? round(($this->total_outstanding / $this->credit_limit) * 100, 2)
                : 0,
        ];
    }
}
