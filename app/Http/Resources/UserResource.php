<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'avatar_url' => $this->avatar_path ? url('storage/' . $this->avatar_path) : null,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'uuid' => $this->store->uuid,
                    'name' => $this->store->name,
                    'slug' => $this->store->slug,
                    'currency' => $this->store->currency,
                    'timezone' => $this->store->timezone,
                ];
            }),
            'branch' => $this->whenLoaded('branch', function () {
                return $this->branch ? [
                    'id' => $this->branch->id,
                    'uuid' => $this->branch->uuid,
                    'name' => $this->branch->name,
                    'is_main' => $this->branch->is_main,
                ] : null;
            }),
        ];
    }
}
