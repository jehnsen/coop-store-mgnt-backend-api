<?php

namespace App\Providers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CreditTransaction;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Observers\CacheInvalidationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register cache invalidation observers
        // Only enable if report caching is enabled in config
        if (config('cache.enable_reports', true)) {
            Sale::observe(CacheInvalidationObserver::class);
            Product::observe(CacheInvalidationObserver::class);
            Customer::observe(CacheInvalidationObserver::class);
            CreditTransaction::observe(CacheInvalidationObserver::class);
            PurchaseOrder::observe(CacheInvalidationObserver::class);
            StockAdjustment::observe(CacheInvalidationObserver::class);
        }
    }
}
