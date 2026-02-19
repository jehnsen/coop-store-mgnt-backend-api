<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanAmortizationScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalDueCentavos  = $this->getRawOriginal('total_due');
        $totalPaidCentavos = $this->getRawOriginal('total_paid');
        $remainingCentavos = $totalDueCentavos - $totalPaidCentavos;
        $isOverdue         = $this->status === 'overdue'
            || (in_array($this->status, ['pending', 'partial']) && $this->due_date < now()->startOfDay());

        return [
            'id'                 => $this->id,
            'payment_number'     => $this->payment_number,
            'due_date'           => $this->due_date?->toDateString(),
            'status'             => $this->status,
            'is_overdue'         => $isOverdue,
            'days_overdue'       => $isOverdue ? (int) Carbon::parse($this->due_date)->diffInDays(Carbon::today()) : 0,

            // All monetary in pesos
            'beginning_balance'  => number_format($this->getRawOriginal('beginning_balance') / 100, 2, '.', ''),
            'principal_due'      => number_format($this->getRawOriginal('principal_due') / 100, 2, '.', ''),
            'interest_due'       => number_format($this->getRawOriginal('interest_due') / 100, 2, '.', ''),
            'total_due'          => number_format($totalDueCentavos / 100, 2, '.', ''),
            'principal_paid'     => number_format($this->getRawOriginal('principal_paid') / 100, 2, '.', ''),
            'interest_paid'      => number_format($this->getRawOriginal('interest_paid') / 100, 2, '.', ''),
            'penalty_paid'       => number_format($this->getRawOriginal('penalty_paid') / 100, 2, '.', ''),
            'total_paid'         => number_format($totalPaidCentavos / 100, 2, '.', ''),
            'remaining_due'      => number_format($remainingCentavos / 100, 2, '.', ''),
            'ending_balance'     => number_format($this->getRawOriginal('ending_balance') / 100, 2, '.', ''),
            'paid_date'          => $this->paid_date?->toDateString(),
        ];
    }
}
