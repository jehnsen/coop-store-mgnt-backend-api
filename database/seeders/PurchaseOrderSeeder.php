<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\User;
use App\Models\PurchaseOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $mainBranch = Branch::where('is_main', true)->first();
        $suppliers = Supplier::all();
        $managers = User::where('role', 'manager')->get();
        $inventoryStaff = User::where('role', 'inventory_staff')->get();
        $users = $managers->merge($inventoryStaff);

        $poCounter = 1;

        // Status distribution:
        // 2 draft, 3 submitted, 2 partial, 3 received

        $poData = [
            // DRAFT POs (2)
            [
                'status' => 'draft',
                'supplier' => $suppliers->random(),
                'order_date' => Carbon::now()->subDays(2),
                'expected_delivery_date' => Carbon::now()->addDays(10),
                'received_date' => null,
                'item_count' => rand(3, 6),
            ],
            [
                'status' => 'draft',
                'supplier' => $suppliers->random(),
                'order_date' => Carbon::now()->subDays(1),
                'expected_delivery_date' => Carbon::now()->addDays(12),
                'received_date' => null,
                'item_count' => rand(4, 7),
            ],

            // SUBMITTED POs (3) - waiting for delivery
            [
                'status' => 'submitted',
                'supplier' => $suppliers->where('name', 'Holcim Philippines Inc.')->first(),
                'order_date' => Carbon::now()->subDays(8),
                'expected_delivery_date' => Carbon::now()->addDays(2),
                'received_date' => null,
                'item_count' => rand(2, 4),
            ],
            [
                'status' => 'submitted',
                'supplier' => $suppliers->where('name', 'SteelAsia Manufacturing Corp.')->first(),
                'order_date' => Carbon::now()->subDays(12),
                'expected_delivery_date' => Carbon::now()->addDays(5),
                'received_date' => null,
                'item_count' => rand(3, 5),
            ],
            [
                'status' => 'submitted',
                'supplier' => $suppliers->where('name', 'Pacific Pipes & Fittings')->first(),
                'order_date' => Carbon::now()->subDays(5),
                'expected_delivery_date' => Carbon::now()->addDays(7),
                'received_date' => null,
                'item_count' => rand(4, 6),
            ],

            // PARTIAL POs (2) - some items received
            [
                'status' => 'partial',
                'supplier' => $suppliers->where('name', 'Boysen Paints Philippines')->first(),
                'order_date' => Carbon::now()->subDays(15),
                'expected_delivery_date' => Carbon::now()->subDays(5),
                'received_date' => null,
                'item_count' => rand(4, 6),
            ],
            [
                'status' => 'partial',
                'supplier' => $suppliers->where('name', 'ABC General Hardware Supply')->first(),
                'order_date' => Carbon::now()->subDays(18),
                'expected_delivery_date' => Carbon::now()->subDays(8),
                'received_date' => null,
                'item_count' => rand(5, 8),
            ],

            // RECEIVED POs (3) - fully completed
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'Holcim Philippines Inc.')->first(),
                'order_date' => Carbon::now()->subDays(35),
                'expected_delivery_date' => Carbon::now()->subDays(25),
                'received_date' => Carbon::now()->subDays(24),
                'item_count' => rand(2, 3),
            ],
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'SteelAsia Manufacturing Corp.')->first(),
                'order_date' => Carbon::now()->subDays(42),
                'expected_delivery_date' => Carbon::now()->subDays(28),
                'received_date' => Carbon::now()->subDays(27),
                'item_count' => rand(3, 5),
            ],
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'ABC General Hardware Supply')->first(),
                'order_date' => Carbon::now()->subDays(50),
                'expected_delivery_date' => Carbon::now()->subDays(40),
                'received_date' => Carbon::now()->subDays(39),
                'item_count' => rand(6, 8),
            ],
        ];

        foreach ($poData as $data) {
            $supplier = $data['supplier'];
            $user = $users->random();

            // Get products from this supplier
            $supplierProducts = DB::table('supplier_products')
                ->where('supplier_id', $supplier->id)
                ->inRandomOrder()
                ->limit($data['item_count'])
                ->get();

            if ($supplierProducts->isEmpty()) {
                continue;
            }

            $totalAmount = 0;
            $poItems = [];

            foreach ($supplierProducts as $sp) {
                $product = Product::find($sp->product_id);

                // Order quantity based on product type
                if (strpos($product->name, 'Cement') !== false) {
                    $quantity = rand(50, 200);
                } elseif (strpos($product->name, 'Deformed Bar') !== false) {
                    $quantity = rand(20, 100);
                } elseif (strpos($product->name, 'CHB') !== false) {
                    $quantity = rand(200, 1000);
                } elseif (strpos($product->name, 'Paint') !== false) {
                    $quantity = rand(20, 50);
                } elseif (strpos($product->name, 'PVC') !== false) {
                    $quantity = rand(30, 100);
                } else {
                    $quantity = rand(10, 50);
                }

                $unitPrice = $sp->supplier_price;
                $lineTotal = $unitPrice * $quantity;
                $totalAmount += $lineTotal;

                // Determine quantity received based on status
                if ($data['status'] === 'draft' || $data['status'] === 'submitted') {
                    $quantityReceived = 0;
                } elseif ($data['status'] === 'partial') {
                    // Received 40-70% of ordered quantity
                    $quantityReceived = (int) ($quantity * rand(40, 70) / 100);
                } else { // received
                    $quantityReceived = $quantity;
                }

                $poItems[] = [
                    'product' => $product,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => $quantityReceived,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            // Determine payment tracking fields based on status
            $paymentStatus = 'unpaid';
            $amountPaid = 0;
            $paymentDueDate = null;
            $paymentCompletedDate = null;

            if ($data['status'] === 'received') {
                // Set payment due date based on supplier's payment terms
                $paymentDueDate = $data['received_date']->copy()->addDays($supplier->payment_terms_days);

                // Randomize payment status for demo data:
                // 50% unpaid, 30% partial, 20% paid
                $rand = rand(1, 100);
                if ($rand <= 20) {
                    // Fully paid
                    $paymentStatus = 'paid';
                    $amountPaid = $totalAmount;
                    $paymentCompletedDate = $data['received_date']->copy()->addDays(rand(5, 25));
                } elseif ($rand <= 50) {
                    // Partially paid
                    $paymentStatus = 'partial';
                    $amountPaid = (int) ($totalAmount * rand(30, 70) / 100);
                }
                // else: remains unpaid (50% chance)
            }

            // Create PO
            $po = PurchaseOrder::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'branch_id' => $mainBranch->id,
                'user_id' => $user->id,
                'po_number' => 'PO-2026-' . str_pad($poCounter++, 6, '0', STR_PAD_LEFT),
                'status' => $data['status'],
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'],
                'received_date' => $data['received_date'],
                'total_amount' => $totalAmount,
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'payment_due_date' => $paymentDueDate,
                'payment_completed_date' => $paymentCompletedDate,
                'notes' => $data['status'] === 'submitted' ? 'Waiting for delivery from supplier' : null,
                'terms_and_conditions' => "Payment terms: {$supplier->payment_terms_days} days\nDelivery lead time: {$supplier->lead_time_days} days",
                'created_at' => $data['order_date'],
                'updated_at' => $data['received_date'] ?? $data['order_date'],
            ]);

            // Create PO items
            foreach ($poItems as $item) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'product_sku' => $item['product']->sku,
                    'quantity_ordered' => $item['quantity_ordered'],
                    'quantity_received' => $item['quantity_received'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'notes' => null,
                    'created_at' => $data['order_date'],
                    'updated_at' => $data['received_date'] ?? $data['order_date'],
                ]);

                // Update product stock if received
                if ($item['quantity_received'] > 0) {
                    $product = $item['product'];
                    $product->current_stock += $item['quantity_received'];
                    $product->save();

                    // Create stock adjustment
                    DB::table('stock_adjustments')->insert([
                        'uuid' => Str::uuid(),
                        'store_id' => $store->id,
                        'branch_id' => $mainBranch->id,
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => 'purchase',
                        'quantity_before' => $product->current_stock - $item['quantity_received'],
                        'quantity_change' => $item['quantity_received'],
                        'quantity_after' => $product->current_stock,
                        'reference_type' => 'App\\Models\\PurchaseOrder',
                        'reference_id' => $po->id,
                        'reason' => 'Purchase order received',
                        'notes' => "PO: {$po->po_number}",
                        'created_at' => $data['received_date'] ?? $data['order_date'],
                        'updated_at' => $data['received_date'] ?? $data['order_date'],
                    ]);
                }
            }
        }

        echo "Created 10 purchase orders with various statuses.\n";
    }
}
