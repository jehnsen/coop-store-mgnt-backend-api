<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatronageRefundBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                   => $this->uuid,
            'period_label'           => $this->period_label,
            'period_from'            => $this->period_from?->toDateString(),
            'period_to'              => $this->period_to?->toDateString(),
            'computation_method'     => $this->computation_method,
            'pr_rate'                => $this->pr_rate,
            'pr_fund'                => number_format($this->getRawOriginal('pr_fund') / 100, 2, '.', ''),
            'total_member_purchases' => number_format($this->getRawOriginal('total_member_purchases') / 100, 2, '.', ''),
            'total_store_sales'      => number_format($this->getRawOriginal('total_store_sales') / 100, 2, '.', ''),
            'total_allocated'        => number_format($this->getRawOriginal('total_allocated') / 100, 2, '.', ''),
            'total_distributed'      => number_format($this->getRawOriginal('total_distributed') / 100, 2, '.', ''),
            'member_count'           => $this->member_count,
            'status'                 => $this->status,
            'approved_by'            => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at'            => $this->approved_at?->toISOString(),
            'notes'                  => $this->notes,
            'allocations'            => PatronageRefundAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }
}
