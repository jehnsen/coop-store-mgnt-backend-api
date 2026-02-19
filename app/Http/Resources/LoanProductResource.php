<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                 => $this->uuid,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'description'          => $this->description,
            'loan_type'            => $this->loan_type,
            'interest_rate'        => (float) $this->interest_rate,    // monthly rate
            'interest_rate_pct'    => round((float) $this->interest_rate * 100, 4), // display as %
            'interest_method'      => $this->interest_method,
            'max_term_months'      => $this->max_term_months,
            'min_amount'           => number_format($this->getRawOriginal('min_amount') / 100, 2, '.', ''),
            'max_amount'           => number_format($this->getRawOriginal('max_amount') / 100, 2, '.', ''),
            'processing_fee_rate'  => (float) $this->processing_fee_rate,
            'processing_fee_pct'   => round((float) $this->processing_fee_rate * 100, 4),
            'service_fee'          => number_format($this->getRawOriginal('service_fee') / 100, 2, '.', ''),
            'requires_collateral'  => (bool) $this->requires_collateral,
            'is_active'            => (bool) $this->is_active,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
