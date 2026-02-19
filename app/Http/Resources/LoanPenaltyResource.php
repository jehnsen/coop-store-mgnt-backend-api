<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanPenaltyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                    => $this->uuid,
            'penalty_type'            => $this->penalty_type,
            'penalty_rate'            => (float) $this->penalty_rate,
            'penalty_rate_pct'        => round((float) $this->penalty_rate * 100, 4),
            'days_overdue'            => $this->days_overdue,
            'penalty_amount'          => number_format($this->getRawOriginal('penalty_amount') / 100, 2, '.', ''),
            'waived_amount'           => number_format($this->getRawOriginal('waived_amount') / 100, 2, '.', ''),
            'net_penalty'             => number_format($this->getRawOriginal('net_penalty') / 100, 2, '.', ''),
            'applied_date'            => $this->applied_date?->toDateString(),
            'is_paid'                 => (bool) $this->is_paid,
            'paid_date'               => $this->paid_date?->toDateString(),
            'waived_at'               => $this->waived_at?->toISOString(),
            'waiver_reason'           => $this->waiver_reason,
            'waived_by_name'          => $this->whenLoaded('waivedBy', fn () => $this->waivedBy?->name),
            'amortization_schedule_id' => $this->amortization_schedule_id,
            'created_at'              => $this->created_at?->toISOString(),
        ];
    }
}
