<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\RolePermission;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * This middleware checks if the authenticated user has the required
     * permission based on their role. Store owners always have full access.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission to check (e.g., 'products.create')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = auth()->user();

        // Owner has all permissions
        if ($user->role === 'owner') {
            return $next($request);
        }

        // Check if the user's role has the required permission
        $hasPermission = RolePermission::where('store_id', $user->store_id)
            ->where('role', $user->role)
            ->where('permission', $permission)
            ->exists();

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
