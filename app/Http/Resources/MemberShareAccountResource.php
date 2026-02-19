<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberShareAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidUpCentavos      = $this->getRawOriginal('total_paid_up_amount');
        $subscribedCentavos  = $this->getRawOriginal('total_subscribed_amount');
        $parValueCentavos    = $this->getRawOriginal('par_value_per_share');

        return [
            'uuid'                   => $this->uuid,
            'account_number'         => $this->account_number,
            'share_type'             => $this->share_type,
            'status'                 => $this->status,

            // Share counts
            'subscribed_shares'      => $this->subscribed_shares,
            'paid_up_shares'         => $parValueCentavos > 0 ? (int) floor($paidUpCentavos / $parValueCentavos) : 0,

            // Monetary fields (pesos)
            'par_value_per_share'    => number_format($parValueCentavos / 100, 2, '.', ''),
            'total_subscribed_amount' => number_format($subscribedCentavos / 100, 2, '.', ''),
            'total_paid_up_amount'   => number_format($paidUpCentavos / 100, 2, '.', ''),
            'remaining_subscription' => number_format(($subscribedCentavos - $paidUpCentavos) / 100, 2, '.', ''),

            // Computed
            'subscription_percentage' => $subscribedCentavos > 0
                ? round(($paidUpCentavos / $subscribedCentavos) * 100, 2)
                : 0.0,
            'is_fully_paid'          => $paidUpCentavos >= $subscribedCentavos,

            // Dates
            'opened_date'            => $this->opened_date?->toDateString(),
            'withdrawn_date'         => $this->withdrawn_date?->toDateString(),

            // Notes
            'notes'                  => $this->notes,

            // Relations
            'customer'               => $this->whenLoaded('customer', fn () => [
                'uuid'      => $this->customer->uuid,
                'name'      => $this->customer->name,
                'member_id' => $this->customer->member_id,
            ]),
            'payments'               => ShareCapitalPaymentResource::collection($this->whenLoaded('payments')),
            'certificates'           => ShareCertificateResource::collection($this->whenLoaded('certificates')),

            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }
}
