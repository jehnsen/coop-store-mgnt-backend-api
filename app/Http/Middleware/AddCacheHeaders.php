<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddCacheHeaders
{
    /**
     * Handle an incoming request and add appropriate cache headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $maxAge = '120'): Response
    {
        $response = $next($request);

        // Only add cache headers to successful GET requests
        if ($request->isMethod('GET') && $response->isSuccessful()) {
            // Check if fresh data was requested
            if ($request->boolean('fresh') || $request->boolean('no_cache')) {
                // No cache for fresh data requests
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
            } else {
                // Add cache headers with specified max-age
                $response->headers->set('Cache-Control', "private, max-age={$maxAge}, must-revalidate");
                $response->headers->set('X-Cache-Hint', "Cached for {$maxAge} seconds. Use ?fresh=1 for live data.");
            }
        }

        return $response;
    }
}
