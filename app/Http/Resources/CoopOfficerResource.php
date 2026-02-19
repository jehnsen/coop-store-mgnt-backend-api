<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoopOfficerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'position'   => $this->position,
            'committee'  => $this->committee,
            'term_from'  => $this->term_from?->toDateString(),
            'term_to'    => $this->term_to?->toDateString(),
            'is_active'  => (bool) $this->is_active,
            'notes'      => $this->notes,
            'member'     => $this->whenLoaded('customer', fn () => $this->customer ? [
                'uuid'      => $this->customer->uuid,
                'name'      => $this->customer->name,
                'member_id' => $this->customer->member_id,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
