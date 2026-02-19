<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $branches = Branch::all();
        $cashiers = User::whereIn('role', ['cashier', 'manager', 'owner'])->get();
        $customers = Customer::all();

        // Get popular products (cement, steel, lumber)
        $cementProducts = Product::where('name', 'like', '%Cement%')->get();
        $steelProducts = Product::where('name', 'like', '%Deformed Bar%')->get();
        $lumberProducts = Product::where('name', 'like', '%Lumber%')->orWhere('name', 'like', '%Plywood%')->get();
        $popularProducts = $cementProducts->merge($steelProducts)->merge($lumberProducts);

        // Get all products for random sales
        $allProducts = Product::all();

        $invoiceCounter = 1;
        $salesGenerated = 0;

        // Generate sales for the last 90 days
        for ($daysAgo = 90; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);

            // Skip Sundays (lower volume) - only 2-3 transactions
            // Saturdays: 5-8 transactions
            // Weekdays: 8-12 transactions
            $dayOfWeek = $date->dayOfWeek;

            if ($dayOfWeek == 0) { // Sunday
                $transactionsToday = rand(2, 3);
            } elseif ($dayOfWeek == 6) { // Saturday
                $transactionsToday = rand(5, 8);
            } else { // Weekdays
                $transactionsToday = rand(8, 12);
            }

            for ($i = 0; $i < $transactionsToday; $i++) {
                $branch = $branches->random();
                $cashier = $cashiers->where('branch_id', $branch->id)->random() ?? $cashiers->random();

                // 20% walk-in, 80% registered customers
                $isWalkIn = rand(1, 100) <= 20;
                if ($isWalkIn) {
                    $customer = $customers->where('code', 'WALK-IN-001')->first();
                } else {
                    $customer = $customers->where('code', '!=', 'WALK-IN-001')->random();
                }

                // Determine price tier based on customer
                if (in_array($customer->code, ['CONT-001', 'CONT-002', 'CONT-003'])) {
                    $priceTier = 'contractor';
                } elseif (in_array($customer->code, ['CUST-002', 'CUST-003', 'CUST-004'])) {
                    $priceTier = rand(1, 100) <= 70 ? 'retail' : 'wholesale';
                } elseif (in_array($customer->code, ['CORP-001', 'CORP-002'])) {
                    $priceTier = 'wholesale';
                } else {
                    $priceTier = 'retail';
                }

                // Determine payment method
                // Cash: 60%, Credit: 25%, GCash: 10%, Bank Transfer: 5%
                $paymentMethodRand = rand(1, 100);
                if ($paymentMethodRand <= 60) {
                    $paymentMethod = 'cash';
                } elseif ($paymentMethodRand <= 85) {
                    $paymentMethod = 'credit';
                } elseif ($paymentMethodRand <= 95) {
                    $paymentMethod = 'gcash';
                } else {
                    $paymentMethod = 'bank_transfer';
                }

                // Only allow credit for customers with credit
                if (!$customer->allow_credit) {
                    $paymentMethod = $paymentMethod === 'credit' ? 'cash' : $paymentMethod;
                }

                // Random time during business hours (7 AM - 6 PM)
                $hour = rand(7, 17);
                $minute = rand(0, 59);
                $saleTime = $date->copy()->setTime($hour, $minute);

                // Determine number of items (1-15, average 4)
                // 40% chance: 1-3 items
                // 40% chance: 4-7 items
                // 15% chance: 8-12 items
                // 5% chance: 13-15 items
                $itemCountRand = rand(1, 100);
                if ($itemCountRand <= 40) {
                    $itemCount = rand(1, 3);
                } elseif ($itemCountRand <= 80) {
                    $itemCount = rand(4, 7);
                } elseif ($itemCountRand <= 95) {
                    $itemCount = rand(8, 12);
                } else {
                    $itemCount = rand(13, 15);
                }

                // Select products (40% chance popular products appear)
                $saleProducts = collect();
                for ($j = 0; $j < $itemCount; $j++) {
                    if (rand(1, 100) <= 40 && $popularProducts->count() > 0) {
                        $product = $popularProducts->random();
                    } else {
                        $product = $allProducts->random();
                    }

                    // Avoid duplicates in same sale
                    if (!$saleProducts->contains('id', $product->id)) {
                        $saleProducts->push($product);
                    }
                }

                // Create sale
                $sale = Sale::create([
                    'uuid' => Str::uuid(),
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'user_id' => $cashier->id,
                    'sale_number' => 'INV-2026-' . str_pad($invoiceCounter++, 6, '0', STR_PAD_LEFT),
                    'price_tier' => $priceTier,
                    'subtotal_amount' => 0, // Will calculate below
                    'discount_type' => null,
                    'discount_value' => 0,
                    'discount_amount' => 0,
                    'vat_amount' => 0, // Will calculate below
                    'total_amount' => 0, // Will calculate below
                    'amount_paid' => 0, // Will calculate below
                    'change_amount' => 0,
                    'payment_status' => $paymentMethod === 'credit' ? 'unpaid' : 'paid',
                    'status' => 'completed',
                    'notes' => null,
                    'created_at' => $saleTime,
                    'updated_at' => $saleTime,
                ]);

                $subtotal = 0;

                // Create sale items
                foreach ($saleProducts as $product) {
                    // Determine quantity based on product type
                    if ($product->name === 'River Sand' || $product->name === 'Washed Sand' || strpos($product->name, 'Gravel') !== false) {
                        $quantity = rand(1, 5); // 1-5 cubic meters
                    } elseif (strpos($product->name, 'Cement') !== false) {
                        $quantity = rand(5, 100); // 5-100 bags
                    } elseif (strpos($product->name, 'CHB') !== false) {
                        $quantity = rand(50, 500); // 50-500 blocks
                    } elseif (strpos($product->name, 'Deformed Bar') !== false) {
                        $quantity = rand(10, 50); // 10-50 lengths
                    } elseif (strpos($product->name, 'Nail') !== false) {
                        $quantity = rand(5, 50); // 5-50 kg
                    } elseif (strpos($product->name, 'PVC Elbow') !== false || strpos($product->name, 'PVC Tee') !== false) {
                        $quantity = rand(10, 100); // 10-100 pcs
                    } else {
                        $quantity = rand(1, 20); // 1-20 general items
                    }

                    // Get correct price based on tier
                    $unitPrice = match($priceTier) {
                        'wholesale' => $product->wholesale_price ?? $product->retail_price,
                        'contractor' => $product->contractor_price ?? $product->retail_price,
                        default => $product->retail_price,
                    };

                    // Random item discount (10% chance, 5-15% off)
                    $hasItemDiscount = rand(1, 100) <= 10;
                    $discountType = null;
                    $discountValue = 0;
                    $discountAmount = 0;

                    if ($hasItemDiscount) {
                        $discountType = 'percentage';
                        $discountValue = rand(5, 15);
                        $discountAmount = (int) (($unitPrice * $quantity * $discountValue) / 100);
                    }

                    $lineTotal = ($unitPrice * $quantity) - $discountAmount;
                    $subtotal += $lineTotal;

                    DB::table('sale_items')->insert([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_type' => $discountType,
                        'discount_value' => $discountValue,
                        'discount_amount' => $discountAmount,
                        'line_total' => $lineTotal,
                        'cost_price' => $product->cost_price,
                        'created_at' => $saleTime,
                        'updated_at' => $saleTime,
                    ]);
                }

                // Calculate VAT (12% inclusive - VAT = Total / 1.12 * 0.12)
                $vatAmount = (int) ($subtotal / 1.12 * 0.12);
                $totalAmount = $subtotal;

                // Update sale with totals
                $sale->update([
                    'subtotal_amount' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $paymentMethod === 'credit' ? 0 : $totalAmount,
                    'change_amount' => 0,
                ]);

                // Create payment record if not credit
                if ($paymentMethod !== 'credit') {
                    DB::table('sale_payments')->insert([
                        'sale_id' => $sale->id,
                        'method' => $paymentMethod,
                        'amount' => $totalAmount,
                        'reference_number' => $paymentMethod === 'gcash' ? 'GCASH-' . rand(100000000, 999999999) : ($paymentMethod === 'bank_transfer' ? 'BANK-' . rand(100000, 999999) : null),
                        'notes' => null,
                        'created_at' => $saleTime,
                        'updated_at' => $saleTime,
                    ]);
                }

                $salesGenerated++;
            }
        }

        echo "Generated {$salesGenerated} sales over the last 90 days.\n";
    }
}
