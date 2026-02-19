<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'                  => $this->uuid,
            'application_number'    => $this->application_number,
            'application_type'      => $this->application_type,
            'application_date'      => $this->application_date?->toDateString(),
            'civil_status'          => $this->civil_status,
            'occupation'            => $this->occupation,
            'employer'              => $this->employer,
            'monthly_income_range'  => $this->monthly_income_range,
            'beneficiary_info'      => $this->beneficiary_info,
            'admission_fee_amount'  => number_format($this->getRawOriginal('admission_fee_amount') / 100, 2, '.', ''),
            'status'                => $this->status,
            'reviewed_by'           => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy?->name),
            'reviewed_at'           => $this->reviewed_at?->toISOString(),
            'rejection_reason'      => $this->rejection_reason,
            'notes'                 => $this->notes,
            'customer'              => $this->whenLoaded('customer', fn () => [
                'uuid'          => $this->customer?->uuid,
                'name'          => $this->customer?->name,
                'member_id'     => $this->customer?->member_id,
                'member_status' => $this->customer?->member_status,
                'phone'         => $this->customer?->phone,
                'mobile'        => $this->customer?->mobile,
                'email'         => $this->customer?->email,
            ]),
            'fees'                  => MembershipFeeResource::collection($this->whenLoaded('fees')),
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
