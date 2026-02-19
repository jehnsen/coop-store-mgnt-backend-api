<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreAccess
{
    /**
     * Handle an incoming request.
     *
     * This middleware automatically injects the authenticated user's store_id
     * into the request for easy access in controllers and other middleware.
     * This is critical for multi-tenancy to ensure data isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // Inject store_id into the request for easy access
            $request->merge(['store_id' => auth()->user()->store_id]);
        }

        return $next($request);
    }
}
