<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingsResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,

            // Branch information
            'branch' => [
                'id' => $this->branch?->id,
                'uuid' => $this->branch?->uuid,
                'name' => $this->branch?->name,
            ],

            // Activity information
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,

            // Computed fields
            'status' => $this->is_active ? 'active' : 'inactive',
            'role_display' => $this->getRoleDisplayName(),

            // Activity stats (when loaded)
            'stats' => [
                'total_sales' => $this->whenHas('total_sales', $this->total_sales),
                'total_transactions' => $this->whenHas('total_transactions', $this->total_transactions),
            ],

            // Permissions (when loaded)
            'permissions' => $this->when(
                isset($this->permissions),
                fn() => $this->permissions
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get role display name
     */
    private function getRoleDisplayName(): string
    {
        return match($this->role) {
            'owner' => 'Owner',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'inventory_staff' => 'Inventory Staff',
            default => ucfirst($this->role),
        };
    }
}
