<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\StockAdjustment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StockAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $mainBranch = Branch::where('is_main', true)->first();
        $products = Product::all();
        $inventoryStaff = User::where('role', 'inventory_staff')->get();
        $managers = User::where('role', 'manager')->get();

        // 1. Create initial_stock entries for all products (matching current_stock)
        echo "Creating initial stock entries for all products...\n";

        foreach ($products as $product) {
            StockAdjustment::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'branch_id' => $mainBranch->id,
                'product_id' => $product->id,
                'user_id' => $managers->first()->id,
                'type' => 'initial_stock',
                'quantity_before' => 0,
                'quantity_change' => $product->current_stock,
                'quantity_after' => $product->current_stock,
                'reference_type' => null,
                'reference_id' => null,
                'reason' => 'Initial stock entry',
                'notes' => 'Opening inventory for ' . $product->name,
                'created_at' => Carbon::now()->subDays(95),
                'updated_at' => Carbon::now()->subDays(95),
            ]);
        }

        // 2. Create 25 physical_count adjustments (small variances)
        echo "Creating physical count adjustments...\n";

        for ($i = 0; $i < 25; $i++) {
            $product = $products->random();
            $daysAgo = rand(10, 80);
            $adjustmentDate = Carbon::now()->subDays($daysAgo);

            $currentStock = $product->current_stock;

            // Small variance: +/- 1-5% of current stock
            $variancePercent = rand(-5, 5);
            $variance = (int) ($currentStock * $variancePercent / 100);

            // Ensure variance is at least -1 or +1 if not zero
            if ($variance == 0) {
                $variance = rand(0, 1) == 0 ? -1 : 1;
            }

            $newStock = max(0, $currentStock + $variance);

            $user = $inventoryStaff->random();

            StockAdjustment::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'branch_id' => $mainBranch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'physical_count',
                'quantity_before' => $currentStock,
                'quantity_change' => $variance,
                'quantity_after' => $newStock,
                'reference_type' => null,
                'reference_id' => null,
                'reason' => $variance > 0 ? 'Physical count found extra stock' : 'Physical count found shortage',
                'notes' => "Monthly inventory count - variance: {$variance}",
                'created_at' => $adjustmentDate,
                'updated_at' => $adjustmentDate,
            ]);

            // Update product stock
            $product->update(['current_stock' => $newStock]);
        }

        // 3. Create 10 damaged goods write-offs
        echo "Creating damaged goods adjustments...\n";

        for ($i = 0; $i < 10; $i++) {
            $product = $products->random();
            $daysAgo = rand(5, 75);
            $adjustmentDate = Carbon::now()->subDays($daysAgo);

            $currentStock = $product->current_stock;

            // Damaged: 1-10 units or 1-3% of stock, whichever is smaller
            $damageAmount = min(rand(1, 10), (int) ($currentStock * rand(1, 3) / 100));
            $damageAmount = max(1, $damageAmount); // At least 1

            if ($currentStock < $damageAmount) {
                $damageAmount = (int) ($currentStock * 0.5); // Max 50% of stock if low
            }

            $newStock = max(0, $currentStock - $damageAmount);

            $user = $inventoryStaff->random();

            $reasons = [
                'Water damage during storage',
                'Product expired',
                'Damaged during handling',
                'Defective items returned by customer',
                'Rust and corrosion',
                'Packaging torn/damaged',
                'Quality control failure',
            ];

            StockAdjustment::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'branch_id' => $mainBranch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'damage',
                'quantity_before' => $currentStock,
                'quantity_change' => -$damageAmount,
                'quantity_after' => $newStock,
                'reference_type' => null,
                'reference_id' => null,
                'reason' => $reasons[array_rand($reasons)],
                'notes' => "Write-off: {$damageAmount} units damaged",
                'created_at' => $adjustmentDate,
                'updated_at' => $adjustmentDate,
            ]);

            // Update product stock
            $product->update(['current_stock' => $newStock]);
        }

        // 4. Create 5 stock_in adjustments (received from supplier, not via PO)
        echo "Creating manual stock-in adjustments...\n";

        for ($i = 0; $i < 5; $i++) {
            $product = $products->random();
            $daysAgo = rand(15, 70);
            $adjustmentDate = Carbon::now()->subDays($daysAgo);

            $currentStock = $product->current_stock;

            // Stock in: 10-100 units depending on product type
            if (strpos($product->name, 'CHB') !== false || strpos($product->name, 'Nail') !== false) {
                $stockInAmount = rand(100, 500);
            } elseif (strpos($product->name, 'Cement') !== false) {
                $stockInAmount = rand(50, 200);
            } else {
                $stockInAmount = rand(10, 100);
            }

            $newStock = $currentStock + $stockInAmount;

            $user = $managers->random();

            StockAdjustment::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'branch_id' => $mainBranch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'stock_in',
                'quantity_before' => $currentStock,
                'quantity_change' => $stockInAmount,
                'quantity_after' => $newStock,
                'reference_type' => null,
                'reference_id' => null,
                'reason' => 'Emergency stock replenishment',
                'notes' => "Manual stock-in: {$stockInAmount} units received",
                'created_at' => $adjustmentDate,
                'updated_at' => $adjustmentDate,
            ]);

            // Update product stock
            $product->update(['current_stock' => $newStock]);
        }

        echo "Stock adjustments created successfully.\n";
    }
}
