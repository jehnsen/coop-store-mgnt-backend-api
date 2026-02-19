<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Branch;

class EnsureBranchAccess
{
    /**
     * Handle an incoming request.
     *
     * This middleware validates that the authenticated user has access
     * to the specified branch. It checks if the branch belongs to the
     * user's store to maintain multi-tenant data isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->route('branch') ?? $request->input('branch_id');

        if ($branchId && auth()->check()) {
            $user = auth()->user();

            // Check if the branch belongs to the user's store
            $branch = Branch::where('id', $branchId)
                ->where('store_id', $user->store_id)
                ->first();

            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch.',
                ], 403);
            }
        }

        return $next($request);
    }
}
