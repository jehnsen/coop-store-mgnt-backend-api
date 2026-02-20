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

        // Status distribution: 2 draft, 3 submitted, 2 partial, 3 received
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
                'supplier' => $suppliers->where('name', 'Bernas Agri-Inputs Trading')->first(),
                'order_date' => Carbon::now()->subDays(8),
                'expected_delivery_date' => Carbon::now()->addDays(2),
                'received_date' => null,
                'item_count' => rand(2, 4),
            ],
            [
                'status' => 'submitted',
                'supplier' => $suppliers->where('name', 'Agriville Fertilizer & Chemical Supply')->first(),
                'order_date' => Carbon::now()->subDays(12),
                'expected_delivery_date' => Carbon::now()->addDays(5),
                'received_date' => null,
                'item_count' => rand(3, 5),
            ],
            [
                'status' => 'submitted',
                'supplier' => $suppliers->where('name', 'Salonga General Merchandise & Trading')->first(),
                'order_date' => Carbon::now()->subDays(5),
                'expected_delivery_date' => Carbon::now()->addDays(7),
                'received_date' => null,
                'item_count' => rand(4, 6),
            ],

            // PARTIAL POs (2) - some items received
            [
                'status' => 'partial',
                'supplier' => $suppliers->where('name', 'AgriChem Central Luzon Distributor')->first(),
                'order_date' => Carbon::now()->subDays(15),
                'expected_delivery_date' => Carbon::now()->subDays(5),
                'received_date' => null,
                'item_count' => rand(4, 6),
            ],
            [
                'status' => 'partial',
                'supplier' => $suppliers->where('name', 'Nueva Ecija Feeds & Livestock Supply')->first(),
                'order_date' => Carbon::now()->subDays(18),
                'expected_delivery_date' => Carbon::now()->subDays(8),
                'received_date' => null,
                'item_count' => rand(3, 5),
            ],

            // RECEIVED POs (3) - fully completed
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'Bernas Agri-Inputs Trading')->first(),
                'order_date' => Carbon::now()->subDays(35),
                'expected_delivery_date' => Carbon::now()->subDays(25),
                'received_date' => Carbon::now()->subDays(24),
                'item_count' => rand(2, 3),
            ],
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'Agriville Fertilizer & Chemical Supply')->first(),
                'order_date' => Carbon::now()->subDays(42),
                'expected_delivery_date' => Carbon::now()->subDays(28),
                'received_date' => Carbon::now()->subDays(27),
                'item_count' => rand(3, 5),
            ],
            [
                'status' => 'received',
                'supplier' => $suppliers->where('name', 'Cabanatuan Agri-Hardware & Supply')->first(),
                'order_date' => Carbon::now()->subDays(50),
                'expected_delivery_date' => Carbon::now()->subDays(40),
                'received_date' => Carbon::now()->subDays(39),
                'item_count' => rand(4, 6),
            ],
        ];

        foreach ($poData as $data) {
            $supplier = $data['supplier'];

            // Skip if supplier not found (shouldn't happen but guard against null)
            if (! $supplier) {
                continue;
            }

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

                if (! $product) {
                    continue;
                }

                // Order quantity based on product category keywords
                $name = strtolower($product->name);
                if (str_contains($name, 'rice') || str_contains($name, 'bigas')) {
                    $quantity = rand(50, 200); // sacks
                } elseif (str_contains($name, 'fertilizer') || str_contains($name, 'urea') || str_contains($name, 'ammonium') || str_contains($name, 'complete')) {
                    $quantity = rand(20, 100); // sacks
                } elseif (str_contains($name, 'feed') || str_contains($name, 'pellet')) {
                    $quantity = rand(20, 80);  // sacks
                } elseif (str_contains($name, 'seed') || str_contains($name, 'binhi')) {
                    $quantity = rand(5, 30);   // bags
                } elseif (str_contains($name, 'pesticide') || str_contains($name, 'herbicide') || str_contains($name, 'insecticide') || str_contains($name, 'fungicide')) {
                    $quantity = rand(5, 30);   // bottles/packs
                } elseif (str_contains($name, 'sardine') || str_contains($name, 'tuna') || str_contains($name, 'noodle') || str_contains($name, 'canned')) {
                    $quantity = rand(48, 240); // pieces
                } elseif (str_contains($name, 'cooking oil') || str_contains($name, 'oil')) {
                    $quantity = rand(12, 60);  // bottles/cans
                } else {
                    $quantity = rand(10, 50);
                }

                $unitPrice = $sp->supplier_price;
                $lineTotal = $unitPrice * $quantity;
                $totalAmount += $lineTotal;

                // Quantity received based on status
                if ($data['status'] === 'draft' || $data['status'] === 'submitted') {
                    $quantityReceived = 0;
                } elseif ($data['status'] === 'partial') {
                    $quantityReceived = (int) ($quantity * rand(40, 70) / 100);
                } else { // received
                    $quantityReceived = $quantity;
                }

                $poItems[] = [
                    'product'           => $product,
                    'quantity_ordered'  => $quantity,
                    'quantity_received' => $quantityReceived,
                    'unit_price'        => $unitPrice,
                    'line_total'        => $lineTotal,
                ];
            }

            if (empty($poItems)) {
                continue;
            }

            // Payment tracking fields
            $paymentStatus = 'unpaid';
            $amountPaid = 0;
            $paymentDueDate = null;
            $paymentCompletedDate = null;

            if ($data['status'] === 'received') {
                $paymentDueDate = $data['received_date']->copy()->addDays($supplier->payment_terms_days);

                $rand = rand(1, 100);
                if ($rand <= 20) {
                    $paymentStatus = 'paid';
                    $amountPaid = $totalAmount;
                    $paymentCompletedDate = $data['received_date']->copy()->addDays(rand(5, 25));
                } elseif ($rand <= 50) {
                    $paymentStatus = 'partial';
                    $amountPaid = (int) ($totalAmount * rand(30, 70) / 100);
                }
            }

            // Create PO
            $po = PurchaseOrder::create([
                'uuid'                   => Str::uuid(),
                'store_id'               => $store->id,
                'supplier_id'            => $supplier->id,
                'branch_id'              => $mainBranch->id,
                'user_id'                => $user->id,
                'po_number'              => 'PO-2026-' . str_pad($poCounter++, 6, '0', STR_PAD_LEFT),
                'status'                 => $data['status'],
                'order_date'             => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'],
                'received_date'          => $data['received_date'],
                'total_amount'           => $totalAmount,
                'payment_status'         => $paymentStatus,
                'amount_paid'            => $amountPaid,
                'payment_due_date'       => $paymentDueDate,
                'payment_completed_date' => $paymentCompletedDate,
                'notes'                  => $data['status'] === 'submitted' ? 'Awaiting delivery from supplier.' : null,
                'terms_and_conditions'   => "Payment terms: {$supplier->payment_terms_days} days\nDelivery lead time: {$supplier->lead_time_days} days",
                'created_at'             => $data['order_date'],
                'updated_at'             => $data['received_date'] ?? $data['order_date'],
            ]);

            // Create PO items
            foreach ($poItems as $item) {
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product']->id,
                    'product_name'      => $item['product']->name,
                    'product_sku'       => $item['product']->sku,
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => $item['quantity_received'],
                    'unit_price'        => $item['unit_price'],
                    'line_total'        => $item['line_total'],
                    'notes'             => null,
                    'created_at'        => $data['order_date'],
                    'updated_at'        => $data['received_date'] ?? $data['order_date'],
                ]);

                // Update product stock if received
                if ($item['quantity_received'] > 0) {
                    $product = $item['product'];
                    $product->current_stock += $item['quantity_received'];
                    $product->save();

                    DB::table('stock_adjustments')->insert([
                        'uuid'             => Str::uuid(),
                        'store_id'         => $store->id,
                        'branch_id'        => $mainBranch->id,
                        'product_id'       => $product->id,
                        'user_id'          => $user->id,
                        'type'             => 'purchase',
                        'quantity_before'  => $product->current_stock - $item['quantity_received'],
                        'quantity_change'  => $item['quantity_received'],
                        'quantity_after'   => $product->current_stock,
                        'reference_type'   => 'App\\Models\\PurchaseOrder',
                        'reference_id'     => $po->id,
                        'reason'           => 'Purchase order received',
                        'notes'            => "PO: {$po->po_number}",
                        'created_at'       => $data['received_date'] ?? $data['order_date'],
                        'updated_at'       => $data['received_date'] ?? $data['order_date'],
                    ]);
                }
            }
        }

        echo "Created purchase orders for SNLSI MPC agri/grocery suppliers.\n";
    }
}
