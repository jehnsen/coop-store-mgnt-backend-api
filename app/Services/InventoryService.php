<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Store;
use App\Models\Branch;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class InventoryService
{
    protected ProductRepositoryInterface $productRepo;

    public function __construct(ProductRepositoryInterface $productRepo)
    {
        $this->productRepo = $productRepo;
    }
    /**
     * Adjust product stock with audit trail.
     *
     * @param Product $product
     * @param string $adjustmentType (add|subtract|set)
     * @param float $quantity
     * @param string $reason
     * @param string|null $notes
     * @param Branch|null $branch
     * @return StockAdjustment
     * @throws \Exception
     */
    public function adjustStock(
        Product $product,
        string $adjustmentType,
        float $quantity,
        string $reason,
        ?string $notes = null,
        ?Branch $branch = null
    ): StockAdjustment {
        return DB::transaction(function () use ($product, $adjustmentType, $quantity, $reason, $notes, $branch) {
            $oldQuantity = $product->current_stock;
            $newQuantity = $oldQuantity;

            // Calculate new quantity based on adjustment type
            switch ($adjustmentType) {
                case 'add':
                    $newQuantity = $oldQuantity + $quantity;
                    $quantityChange = $quantity;
                    break;
                case 'subtract':
                    $newQuantity = $oldQuantity - $quantity;
                    $quantityChange = -$quantity;

                    // Check if negative stock is allowed
                    if ($newQuantity < 0 && !$product->allow_negative_stock) {
                        throw new \Exception("Insufficient stock. Current stock: {$oldQuantity}, requested: {$quantity}");
                    }
                    break;
                case 'set':
                    $newQuantity = $quantity;
                    $quantityChange = $quantity - $oldQuantity;
                    break;
                default:
                    throw new \Exception("Invalid adjustment type: {$adjustmentType}");
            }

            // Update product stock
            $product->update(['current_stock' => $newQuantity]);

            // Create stock adjustment record for audit trail
            $stockAdjustment = StockAdjustment::create([
                'store_id' => $product->store_id,
                'branch_id' => $branch?->id ?? Auth::user()->branch_id,
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'adjustment_type' => $adjustmentType,
                'quantity_before' => $oldQuantity,
                'quantity_change' => $quantityChange,
                'quantity_after' => $newQuantity,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            return $stockAdjustment;
        });
    }

    /**
     * Deduct stock for a sale or other consumption.
     *
     * @param Product $product
     * @param float $quantity
     * @param string $reason
     * @param Branch|null $branch
     * @return StockAdjustment
     * @throws \Exception
     */
    public function deductStock(
        Product $product,
        float $quantity,
        string $reason = 'Sale',
        ?Branch $branch = null
    ): StockAdjustment {
        // Only deduct if product tracks inventory
        if (!$product->track_inventory) {
            throw new \Exception("Product does not track inventory");
        }

        return $this->adjustStock($product, 'subtract', $quantity, $reason, null, $branch);
    }

    /**
     * Restore stock for void/refund.
     *
     * @param Product $product
     * @param float $quantity
     * @param string $reason
     * @param Branch|null $branch
     * @return StockAdjustment
     */
    public function restoreStock(
        Product $product,
        float $quantity,
        string $reason = 'Void/Refund',
        ?Branch $branch = null
    ): StockAdjustment {
        return $this->adjustStock($product, 'add', $quantity, $reason, null, $branch);
    }

    /**
     * Get products below reorder point.
     *
     * @param Store $store
     * @return Collection
     */
    public function getLowStockProducts(Store $store): Collection
    {
        // Use repository's getLowStock with high limit to get all products
        // The store filtering is handled by the repository's multi-tenancy
        return $this->productRepo->getLowStock(PHP_INT_MAX);
    }

    /**
     * Get products with no sales movement in specified days.
     *
     * @param Store $store
     * @param int $days
     * @return Collection
     */
    public function getDeadStockProducts(Store $store, int $days = 90): Collection
    {
        // Repository's getDeadStock returns paginated results
        // Get all results by using a large per_page value and extracting the items
        $paginator = $this->productRepo->getDeadStock($days, PHP_INT_MAX);
        return $paginator->getCollection();
    }

    /**
     * Get stock movement history for a product.
     *
     * @param Product $product
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return Collection
     */
    public function getStockHistory(
        Product $product,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $query = StockAdjustment::where('product_id', $product->id)
            ->with(['user', 'branch']);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Validate stock availability for multiple products.
     *
     * @param array $items Array of ['product_id' => uuid, 'quantity' => float]
     * @return array ['available' => bool, 'errors' => array]
     */
    public function validateStockAvailability(array $items): array
    {
        $errors = [];
        $available = true;

        foreach ($items as $item) {
            $product = $this->productRepo->findByUuid($item['product_id']);

            if (!$product) {
                $errors[] = "Product {$item['product_id']} not found";
                $available = false;
                continue;
            }

            // Skip validation if product doesn't track inventory
            if (!$product->track_inventory) {
                continue;
            }

            // Check if negative stock is allowed
            if (!$product->allow_negative_stock && $product->current_stock < $item['quantity']) {
                $errors[] = "Insufficient stock for {$product->name}. Available: {$product->current_stock}, Requested: {$item['quantity']}";
                $available = false;
            }
        }

        return [
            'available' => $available,
            'errors' => $errors,
        ];
    }

    /**
     * Get inventory valuation (total value of stock at cost price).
     *
     * @param Store $store
     * @return array ['total_value' => int, 'product_count' => int, 'total_items' => float]
     */
    public function getInventoryValuation(Store $store): array
    {
        $valuation = $this->productRepo->getInventoryValuation();

        return [
            'total_value' => $valuation['total_cost_value'], // in centavos
            'total_value_pesos' => $valuation['total_cost_value'] / 100, // in pesos
            'product_count' => $valuation['total_products'],
            'total_items' => $valuation['total_units'],
        ];
    }

    /**
     * Perform physical stock count and create adjustments.
     *
     * @param array $counts Array of ['product_uuid' => uuid, 'counted_quantity' => float, 'notes' => string]
     * @param Branch|null $branch
     * @return array ['adjustments' => Collection, 'summary' => array]
     */
    public function performStockCount(array $counts, ?Branch $branch = null): array
    {
        $adjustments = collect();
        $summary = [
            'total_products' => count($counts),
            'adjustments_made' => 0,
            'value_difference' => 0,
        ];

        DB::transaction(function () use ($counts, $branch, &$adjustments, &$summary) {
            foreach ($counts as $count) {
                $product = $this->productRepo->findByUuid($count['product_uuid']);

                if (!$product) {
                    continue;
                }

                $systemQuantity = $product->current_stock;
                $countedQuantity = $count['counted_quantity'];
                $difference = $countedQuantity - $systemQuantity;

                // Only create adjustment if there's a difference
                if ($difference != 0) {
                    $adjustmentType = $difference > 0 ? 'add' : 'subtract';
                    $quantity = abs($difference);

                    $adjustment = $this->adjustStock(
                        $product,
                        $adjustmentType,
                        $quantity,
                        'Physical stock count',
                        $count['notes'] ?? "Counted: {$countedQuantity}, System: {$systemQuantity}",
                        $branch
                    );

                    $adjustments->push($adjustment);
                    $summary['adjustments_made']++;
                    $summary['value_difference'] += ($difference * $product->cost_price);
                }
            }
        });

        return [
            'adjustments' => $adjustments,
            'summary' => $summary,
        ];
    }

    /**
     * Bulk update stock for multiple products.
     *
     * @param array $updates Array of ['product_uuid' => uuid, 'quantity' => float, 'reason' => string]
     * @param string $adjustmentType
     * @return Collection
     */
    public function bulkAdjustStock(array $updates, string $adjustmentType = 'set'): Collection
    {
        $adjustments = collect();

        DB::transaction(function () use ($updates, $adjustmentType, &$adjustments) {
            foreach ($updates as $update) {
                $product = $this->productRepo->findByUuid($update['product_uuid']);

                if (!$product) {
                    continue;
                }

                $adjustment = $this->adjustStock(
                    $product,
                    $adjustmentType,
                    $update['quantity'],
                    $update['reason'] ?? 'Bulk adjustment',
                    $update['notes'] ?? null
                );

                $adjustments->push($adjustment);
            }
        });

        return $adjustments;
    }
}
