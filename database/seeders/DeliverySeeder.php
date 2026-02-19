<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\User;
use App\Models\Delivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $branches = Branch::all();

        // Get sales with large amounts (likely to need delivery) from contractor/corporate customers
        $contractorCustomers = Customer::whereIn('code', ['CONT-001', 'CONT-002', 'CONT-003', 'CORP-001', 'CORP-002'])->pluck('id');

        $largeSales = Sale::whereIn('customer_id', $contractorCustomers)
            ->where('total_amount', '>', 5000000) // More than ₱50,000
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($largeSales->count() < 15) {
            // If not enough large sales, get any sales from these customers
            $largeSales = Sale::whereIn('customer_id', $contractorCustomers)
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get();
        }

        $deliveryDrivers = User::whereIn('role', ['manager', 'inventory_staff'])->get();

        $deliveryCounter = 1;

        // Status distribution:
        // 3 preparing, 5 dispatched, 2 in_transit, 4 delivered, 1 failed

        $statusDistribution = [
            'preparing', 'preparing', 'preparing',
            'dispatched', 'dispatched', 'dispatched', 'dispatched', 'dispatched',
            'in_transit', 'in_transit',
            'delivered', 'delivered', 'delivered', 'delivered',
            'failed',
        ];

        shuffle($statusDistribution);

        for ($i = 0; $i < min(15, $largeSales->count()); $i++) {
            $sale = $largeSales[$i];
            $customer = Customer::find($sale->customer_id);
            $status = $statusDistribution[$i];

            $saleDate = Carbon::parse($sale->created_at);
            $scheduledDate = $saleDate->copy()->addDays(rand(1, 3));

            $dispatchedAt = null;
            $deliveredAt = null;

            // Set timestamps based on status
            if ($status === 'preparing') {
                // Still preparing, no dispatch yet
                $dispatchedAt = null;
                $deliveredAt = null;
            } elseif ($status === 'dispatched') {
                // Dispatched but not yet delivered
                $dispatchedAt = $scheduledDate->copy()->addHours(rand(6, 10));
                $deliveredAt = null;
            } elseif ($status === 'in_transit') {
                // Dispatched and currently in transit
                $dispatchedAt = $scheduledDate->copy()->addHours(rand(6, 10));
                $deliveredAt = null;
            } elseif ($status === 'delivered') {
                // Successfully delivered
                $dispatchedAt = $scheduledDate->copy()->addHours(rand(6, 10));
                $deliveredAt = $dispatchedAt->copy()->addHours(rand(2, 6));
            } elseif ($status === 'failed') {
                // Delivery failed
                $dispatchedAt = $scheduledDate->copy()->addHours(rand(6, 10));
                $deliveredAt = null;
            }

            // Assign driver for non-preparing deliveries
            $assignedTo = in_array($status, ['preparing']) ? null : $deliveryDrivers->random()->id;

            $deliveryAddress = $customer->address ?? '123 Construction Site, Quezon City';
            $deliveryCity = $customer->city ?? 'Quezon City';
            $deliveryProvince = $customer->province ?? 'Metro Manila';
            $contactPerson = $customer->type === 'business' ? ($customer->company_name . ' - Site Manager') : $customer->name;
            $contactPhone = $customer->mobile ?? $customer->phone ?? '0917-123-4567';

            $receivedBy = null;
            $deliveryNotes = null;
            $failureReason = null;

            if ($status === 'delivered') {
                $receivedBy = $contactPerson;
                $deliveryNotes = 'Delivered successfully. All items checked and accounted for.';
            } elseif ($status === 'failed') {
                $failureReason = 'Customer not available at delivery address. Rescheduling required.';
            } elseif ($status === 'in_transit') {
                $deliveryNotes = 'En route to delivery location. ETA: 1-2 hours.';
            } elseif ($status === 'dispatched') {
                $deliveryNotes = 'Loaded and ready for delivery.';
            }

            Delivery::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'customer_id' => $customer->id,
                'assigned_to' => $assignedTo,
                'delivery_number' => 'DEL-2026-' . str_pad($deliveryCounter++, 6, '0', STR_PAD_LEFT),
                'status' => $status,
                'delivery_address' => $deliveryAddress,
                'delivery_city' => $deliveryCity,
                'delivery_province' => $deliveryProvince,
                'contact_person' => $contactPerson,
                'contact_phone' => $contactPhone,
                'scheduled_date' => $scheduledDate,
                'dispatched_at' => $dispatchedAt,
                'delivered_at' => $deliveredAt,
                'proof_of_delivery_path' => $status === 'delivered' ? 'deliveries/pod-' . Str::random(10) . '.jpg' : null,
                'received_by' => $receivedBy,
                'delivery_notes' => $deliveryNotes,
                'failure_reason' => $failureReason,
                'created_at' => $saleDate,
                'updated_at' => $deliveredAt ?? $dispatchedAt ?? $saleDate,
            ]);

            // Get delivery ID that was just created
            $lastDelivery = Delivery::latest('id')->first();

            // Get sale items for delivery_items table
            $saleItems = DB::table('sale_items')->where('sale_id', $sale->id)->get();

            foreach ($saleItems as $saleItem) {
                DB::table('delivery_items')->insert([
                    'delivery_id' => $lastDelivery->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_name' => $saleItem->product_name,
                    'quantity' => $saleItem->quantity,
                    'notes' => null,
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);
            }
        }

        echo "Created 15 deliveries with various statuses.\n";
    }
}
