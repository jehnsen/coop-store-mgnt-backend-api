<?php

namespace App\Observers;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer to automatically invalidate cache when models change.
 * Attach this observer to models that affect reports and dashboard data.
 */
class CacheInvalidationObserver
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Invalidate relevant caches based on model type.
     */
    protected function invalidateCache(Model $model): void
    {
        // Get model class name
        $modelClass = class_basename($model);

        // Get store_id if available
        $storeId = $model->store_id ?? null;

        // Map model to entity type for cache invalidation
        $entityTypeMap = [
            'Sale' => 'sales',
            'SaleItem' => 'sales',
            'Product' => 'products',
            'Customer' => 'customers',
            'CreditTransaction' => 'credit_transactions',
            'PurchaseOrder' => 'purchase_orders',
            'StockAdjustment' => 'stock_adjustments',
            'PayableTransaction' => 'purchase_orders',
        ];

        $entityType = $entityTypeMap[$modelClass] ?? null;

        if ($entityType) {
            $this->cacheService->invalidateForEntity($entityType, $storeId);
        }
    }
}
