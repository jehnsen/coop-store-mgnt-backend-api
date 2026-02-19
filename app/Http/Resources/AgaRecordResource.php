<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgaRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'aga_number'         => $this->aga_number,
            'meeting_type'       => $this->meeting_type,
            'meeting_year'       => $this->meeting_year,
            'meeting_date'       => $this->meeting_date?->toDateString(),
            'venue'              => $this->venue,
            'total_members'      => $this->total_members,
            'members_present'    => $this->members_present,
            'members_via_proxy'  => $this->members_via_proxy,
            'quorum_percentage'  => $this->quorum_percentage,
            'quorum_achieved'    => (bool) $this->quorum_achieved,
            'presiding_officer'  => $this->presiding_officer,
            'secretary'          => $this->secretary,
            'agenda'             => $this->agenda ?? [],
            'resolutions_passed' => $this->resolutions_passed ?? [],
            'minutes_text'       => $this->minutes_text,
            'status'             => $this->status,
            'finalized_by'       => $this->whenLoaded('finalizedBy', fn () => $this->finalizedBy?->name),
            'finalized_at'       => $this->finalized_at?->toISOString(),
            'notes'              => $this->notes,
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
