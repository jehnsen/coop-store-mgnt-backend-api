<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShareCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'certificate_number'    => $this->certificate_number,
            'shares_covered'        => $this->shares_covered,
            'face_value'            => number_format($this->getRawOriginal('face_value') / 100, 2, '.', ''),
            'issue_date'            => $this->issue_date?->toDateString(),
            'status'                => $this->status,
            'cancelled_at'          => $this->cancelled_at?->toISOString(),
            'cancellation_reason'   => $this->cancellation_reason,
            'issued_by_name'        => $this->whenLoaded('issuedBy', fn () => $this->issuedBy->name ?? null),
            'cancelled_by_name'     => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy?->name),
            'created_at'            => $this->created_at?->toISOString(),
        ];
    }
}
