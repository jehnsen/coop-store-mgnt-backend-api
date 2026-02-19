<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSettingsResource extends JsonResource
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
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'tin' => $this->tin,
            'bir_permit' => $this->bir_permit,
            'vat_registered' => (bool) $this->vat_registered,
            'logo_url' => $this->logo_url,

            // Settings
            'vat_rate' => $this->vat_rate ?? 12,
            'vat_inclusive' => (bool) ($this->vat_inclusive ?? true),
            'is_bmbe' => (bool) ($this->is_bmbe ?? false),
            'currency' => $this->currency ?? 'PHP',
            'timezone' => $this->timezone ?? 'Asia/Manila',

            // Computed fields
            'stats' => [
                'total_users' => $this->whenHas('users_count', $this->users_count),
                'active_users' => $this->whenHas('active_users_count', $this->active_users_count),
                'total_branches' => $this->whenHas('branches_count', $this->branches_count),
                'storage_used_mb' => $this->whenHas('storage_used', $this->storage_used),
            ],

            // Payment gateway configuration (masked)
            'payment_methods' => [
                'cash_enabled' => true,
                'gcash_enabled' => (bool) ($this->gcash_enabled ?? false),
                'gcash_configured' => !empty($this->gcash_api_key),
                'maya_enabled' => (bool) ($this->maya_enabled ?? false),
                'maya_configured' => !empty($this->maya_api_key),
                'card_enabled' => (bool) ($this->card_enabled ?? false),
                'bank_enabled' => (bool) ($this->bank_enabled ?? false),
                'credit_enabled' => (bool) ($this->credit_enabled ?? true),
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
