<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Cache TTL configurations (in minutes)
     */
    private const CACHE_TTL = [
        'dashboard_summary' => 2,        // 2 minutes - frequently changing
        'sales_trend' => 5,              // 5 minutes - less frequent
        'top_products' => 5,             // 5 minutes
        'credit_aging' => 10,            // 10 minutes - expensive query
        'inventory_valuation' => 10,    // 10 minutes - expensive query
        'reports' => 15,                 // 15 minutes - for complex reports
    ];

    /**
     * Cache tags for organized invalidation
     */
    private const CACHE_TAGS = [
        'sales' => ['dashboard', 'sales_reports', 'products'],
        'products' => ['dashboard', 'inventory_reports'],
        'customers' => ['dashboard', 'credit_reports'],
        'credit_transactions' => ['credit_reports'],
        'purchase_orders' => ['inventory_reports', 'ap_reports'],
        'stock_adjustments' => ['inventory_reports', 'dashboard'],
    ];

    /**
     * Remember a value in cache with automatic tagging.
     *
     * @param string $key
     * @param string $type
     * @param callable $callback
     * @param int|null $storeId
     * @return mixed
     */
    public function remember(string $key, string $type, callable $callback, ?int $storeId = null)
    {
        // Build cache key with store isolation
        $cacheKey = $this->buildCacheKey($key, $storeId);

        // Get TTL for this type
        $ttl = $this->getTTL($type);

        // Get tags for this type
        $tags = $this->getTags($key);

        try {
            if (!empty($tags)) {
                // Use tagged cache for easier invalidation
                return Cache::tags($tags)->remember($cacheKey, now()->addMinutes($ttl), $callback);
            } else {
                // Use regular cache without tags
                return Cache::remember($cacheKey, now()->addMinutes($ttl), $callback);
            }
        } catch (\Exception $e) {
            // If cache fails, execute callback directly
            Log::warning('Cache operation failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
            return $callback();
        }
    }

    /**
     * Forget a specific cache key.
     *
     * @param string $key
     * @param int|null $storeId
     * @return bool
     */
    public function forget(string $key, ?int $storeId = null): bool
    {
        $cacheKey = $this->buildCacheKey($key, $storeId);
        return Cache::forget($cacheKey);
    }

    /**
     * Invalidate cache by tags.
     *
     * @param string|array $tags
     * @return bool
     */
    public function invalidateByTags($tags): bool
    {
        try {
            $tags = is_array($tags) ? $tags : [$tags];
            Cache::tags($tags)->flush();
            return true;
        } catch (\Exception $e) {
            Log::warning('Cache invalidation failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Invalidate cache when specific entity changes.
     *
     * @param string $entityType (sales, products, customers, etc.)
     * @param int|null $storeId
     * @return void
     */
    public function invalidateForEntity(string $entityType, ?int $storeId = null): void
    {
        $tags = self::CACHE_TAGS[$entityType] ?? [];

        if (!empty($tags)) {
            $this->invalidateByTags($tags);
        }

        // Also clear specific store-based caches
        if ($storeId) {
            $this->clearStoreCache($storeId, $entityType);
        }
    }

    /**
     * Clear all cache for a specific store.
     *
     * @param int $storeId
     * @param string|null $entityType
     * @return void
     */
    public function clearStoreCache(int $storeId, ?string $entityType = null): void
    {
        $pattern = $entityType
            ? "store:{$storeId}:{$entityType}:*"
            : "store:{$storeId}:*";

        // Note: This requires a cache driver that supports pattern-based deletion
        // For Redis/Memcached this works, for file cache it's less efficient
        try {
            // Clear by pattern if supported, otherwise rely on tags
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $keys = Cache::getRedis()->keys($pattern);
                if (!empty($keys)) {
                    Cache::getRedis()->del($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Pattern-based cache clearing not supported', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build cache key with store isolation.
     *
     * @param string $key
     * @param int|null $storeId
     * @return string
     */
    private function buildCacheKey(string $key, ?int $storeId = null): string
    {
        if ($storeId) {
            return "store:{$storeId}:{$key}";
        }
        return $key;
    }

    /**
     * Get TTL for cache type.
     *
     * @param string $type
     * @return int
     */
    private function getTTL(string $type): int
    {
        return self::CACHE_TTL[$type] ?? self::CACHE_TTL['reports'];
    }

    /**
     * Get cache tags based on key pattern.
     *
     * @param string $key
     * @return array
     */
    private function getTags(string $key): array
    {
        // Determine tags based on key patterns
        if (str_contains($key, 'dashboard')) {
            return ['dashboard'];
        }

        if (str_contains($key, 'sales') || str_contains($key, 'revenue')) {
            return ['sales_reports'];
        }

        if (str_contains($key, 'inventory') || str_contains($key, 'stock')) {
            return ['inventory_reports'];
        }

        if (str_contains($key, 'credit') || str_contains($key, 'aging')) {
            return ['credit_reports'];
        }

        if (str_contains($key, 'ap') || str_contains($key, 'payable')) {
            return ['ap_reports'];
        }

        return [];
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('cache.enable_reports', true);
    }

    /**
     * Get cache statistics for monitoring.
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'enabled' => $this->isEnabled(),
            'ttl_config' => self::CACHE_TTL,
            'tags' => self::CACHE_TAGS,
        ];
    }
}
