<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchSettingsResource extends JsonResource
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
            'uuid' => $this->uuid,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'is_main' => (bool) $this->is_main,
            'is_active' => (bool) $this->is_active,

            // Relationships count
            'users_count' => $this->whenCounted('users'),

            // Computed fields
            'status' => $this->is_active ? 'active' : 'inactive',
            'type' => $this->is_main ? 'main' : 'branch',

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
