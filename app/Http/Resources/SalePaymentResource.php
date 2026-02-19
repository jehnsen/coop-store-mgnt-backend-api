<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
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
            'method' => $this->method,
            'amount' => number_format($this->amount / 100, 2, '.', ''),
            'reference_number' => $this->reference_number,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
