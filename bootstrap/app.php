<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Configure rate limiters
            RateLimiter::for('api', function ($request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'store.access' => \App\Http\Middleware\EnsureStoreAccess::class,
            'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'log.activity' => \App\Http\Middleware\LogActivity::class,
            'cache.headers' => \App\Http\Middleware\AddCacheHeaders::class,
        ]);

        // Configure API middleware group
        // Note: HandleCors middleware is automatically included in Laravel 11 global middleware
        // CORS settings are configured in config/cors.php with environment-based origins
        // Set CORS_ALLOWED_ORIGIN_1 and CORS_ALLOWED_ORIGIN_2 in your .env file
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureStoreAccess::class, // Auto-add for all API routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
