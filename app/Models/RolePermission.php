<?php

namespace App\Models;

use App\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'role',
        'permission',
        'store_id',
    ];

    // Scopes
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    // Helper method to check if a role has a specific permission
    public static function hasPermission(string $role, string $permission, ?int $storeId = null): bool
    {
        $query = static::where('role', $role)->where('permission', $permission);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->exists();
    }
}
