<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managers = User::where('role', 'manager')->get();
        $owner = User::where('role', 'owner')->first();

        // 1. LOW STOCK NOTIFICATIONS (5 products below reorder point)
        echo "Creating low stock notifications...\n";

        // Find or create products below reorder point
        $lowStockProducts = Product::whereRaw('current_stock < reorder_point')
            ->limit(5)
            ->get();

        // If not enough, manually set some products to low stock
        if ($lowStockProducts->count() < 5) {
            $additionalProducts = Product::inRandomOrder()
                ->limit(5 - $lowStockProducts->count())
                ->get();

            foreach ($additionalProducts as $product) {
                $product->update(['current_stock' => $product->reorder_point * 0.5]);
                $lowStockProducts->push($product);
            }
        }

        foreach ($lowStockProducts as $product) {
            $stockPercentage = ($product->current_stock / $product->reorder_point) * 100;

            // Notify all managers and owner
            $usersToNotify = $managers->merge([$owner]);

            foreach ($usersToNotify as $user) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid(),
                    'type' => 'App\\Notifications\\LowStockNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => 'Low Stock Alert',
                        'message' => "Product '{$product->name}' is running low on stock",
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'current_stock' => $product->current_stock,
                        'reorder_point' => $product->reorder_point,
                        'stock_percentage' => round($stockPercentage, 2),
                        'action_url' => '/products/' . $product->id,
                        'severity' => $stockPercentage < 50 ? 'critical' : 'warning',
                    ]),
                    'read_at' => null,
                    'created_at' => Carbon::now()->subDays(rand(1, 5)),
                    'updated_at' => Carbon::now()->subDays(rand(1, 5)),
                ]);
            }
        }

        // 2. OVERDUE CREDIT NOTIFICATIONS (3 customers with overdue balances)
        echo "Creating overdue credit notifications...\n";

        // Find credit transactions that are overdue (distinct customers)
        $overdueCustomerIds = CreditTransaction::where('type', 'charge')
            ->where('due_date', '<', Carbon::now())
            ->whereNull('paid_date')
            ->distinct()
            ->limit(3)
            ->pluck('customer_id');

        foreach ($overdueCustomerIds as $customerId) {
            $customer = Customer::find($customerId);
            $transaction = CreditTransaction::where('customer_id', $customerId)
                ->where('type', 'charge')
                ->where('due_date', '<', Carbon::now())
                ->whereNull('paid_date')
                ->first();
            $daysOverdue = Carbon::now()->diffInDays(Carbon::parse($transaction->due_date));

            // Calculate total overdue amount for this customer
            $totalOverdue = CreditTransaction::where('customer_id', $customer->id)
                ->where('type', 'charge')
                ->where('due_date', '<', Carbon::now())
                ->whereNull('paid_date')
                ->sum('amount');

            // Notify all managers and owner
            $usersToNotify = $managers->merge([$owner]);

            foreach ($usersToNotify as $user) {
                DB::table('notifications')->insert([
                    'id' => Str::uuid(),
                    'type' => 'App\\Notifications\\OverdueCreditNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => 'Overdue Payment Alert',
                        'message' => "Customer '{$customer->name}' has overdue payments",
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'customer_code' => $customer->code,
                        'total_overdue_amount' => $totalOverdue,
                        'days_overdue' => $daysOverdue,
                        'due_date' => $transaction->due_date,
                        'action_url' => '/customers/' . $customer->id . '/credit',
                        'severity' => $daysOverdue > 30 ? 'critical' : 'warning',
                    ]),
                    'read_at' => null,
                    'created_at' => Carbon::now()->subDays(rand(1, 3)),
                    'updated_at' => Carbon::now()->subDays(rand(1, 3)),
                ]);
            }
        }

        // 3. DELIVERY UPDATE NOTIFICATIONS (2 recent deliveries)
        echo "Creating delivery update notifications...\n";

        // Get recent deliveries that were dispatched
        $recentDeliveries = DB::table('deliveries')
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get();

        foreach ($recentDeliveries as $delivery) {
            $customer = Customer::find($delivery->customer_id);

            // Notify the assigned driver and managers
            $usersToNotify = collect();

            if ($delivery->assigned_to) {
                $usersToNotify->push(User::find($delivery->assigned_to));
            }

            $usersToNotify = $usersToNotify->merge($managers);

            foreach ($usersToNotify as $user) {
                if (!$user) continue;

                DB::table('notifications')->insert([
                    'id' => Str::uuid(),
                    'type' => 'App\\Notifications\\DeliveryUpdateNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => 'Delivery Update',
                        'message' => "Delivery {$delivery->delivery_number} is {$delivery->status}",
                        'delivery_id' => $delivery->id,
                        'delivery_number' => $delivery->delivery_number,
                        'status' => $delivery->status,
                        'customer_name' => $customer->name,
                        'delivery_address' => $delivery->delivery_address,
                        'scheduled_date' => $delivery->scheduled_date,
                        'action_url' => '/deliveries/' . $delivery->id,
                        'severity' => 'info',
                    ]),
                    'read_at' => $user->role === 'owner' ? Carbon::now()->subHours(rand(1, 12)) : null, // Owner has read some
                    'created_at' => Carbon::parse($delivery->dispatched_at ?? $delivery->created_at)->addMinutes(rand(10, 60)),
                    'updated_at' => Carbon::parse($delivery->dispatched_at ?? $delivery->created_at)->addMinutes(rand(10, 60)),
                ]);
            }
        }

        echo "Notifications created successfully.\n";
    }
}
