<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogActivity
{
    /**
     * Routes to exclude from activity logging.
     * These are typically high-frequency read operations.
     *
     * @var array
     */
    protected array $excludedRoutes = [
        'api/v1/auth/me',
        'api/v1/notifications',
    ];

    /**
     * Handle an incoming request.
     *
     * This middleware automatically logs API activity for authenticated users.
     * It captures the action, user, resource, and other relevant details.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users and successful requests
        if (auth()->check() && $response->isSuccessful() && !$this->shouldExclude($request)) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * Determine if the current route should be excluded from logging.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldExclude(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the activity to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function logActivity(Request $request, Response $response): void
    {
        try {
            $action = $this->determineAction($request);

            ActivityLog::create([
                'store_id' => auth()->user()->store_id,
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $this->generateDescription($request, $action),
                'subject_type' => $this->extractSubjectType($request),
                'subject_id' => $this->extractSubjectId($request),
                'properties' => $this->extractProperties($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the request
            Log::error('Activity logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine the action type based on HTTP method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function determineAction(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'viewed',
        };
    }

    /**
     * Generate a human-readable description of the action.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $action
     * @return string
     */
    protected function generateDescription(Request $request, string $action): string
    {
        $resource = $this->extractResourceName($request);
        $user = auth()->user()->name;

        return "{$user} {$action} {$resource}";
    }

    /**
     * Extract resource name from the request path.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function extractResourceName(Request $request): string
    {
        $path = $request->path();
        $segments = explode('/', $path);

        // Try to extract resource name from path (e.g., api/v1/products -> products)
        return $segments[2] ?? 'resource';
    }

    /**
     * Extract subject type (model class) from the resource name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function extractSubjectType(Request $request): ?string
    {
        $resource = $this->extractResourceName($request);

        // Map resource names to model classes
        $modelMap = [
            'products' => 'App\Models\Product',
            'sales' => 'App\Models\Sale',
            'customers' => 'App\Models\Customer',
            'suppliers' => 'App\Models\Supplier',
            'purchase-orders' => 'App\Models\PurchaseOrder',
            'deliveries' => 'App\Models\Delivery',
            'categories' => 'App\Models\Category',
            'brands' => 'App\Models\Brand',
            'branches' => 'App\Models\Branch',
            'users' => 'App\Models\User',
            'inventory' => 'App\Models\Inventory',
            'stock-adjustments' => 'App\Models\StockAdjustment',
            'credit-transactions' => 'App\Models\CreditTransaction',
        ];

        return $modelMap[$resource] ?? null;
    }

    /**
     * Extract subject ID from route parameters.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int|null
     */
    protected function extractSubjectId(Request $request): ?int
    {
        // Try to extract ID from route parameters
        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $param) {
                if (is_numeric($param)) {
                    return (int) $param;
                }
            }
        }

        return null;
    }

    /**
     * Extract properties (request data) for logging.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|null
     */
    protected function extractProperties(Request $request): ?array
    {
        // Log request data for creates/updates (exclude sensitive fields)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $request->except(['password', 'password_confirmation', 'token', '_token']);
        }

        return null;
    }
}
