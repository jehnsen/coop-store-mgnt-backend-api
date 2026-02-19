<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PayableTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PayableTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seed payable transactions (AP invoices and payments) for existing purchase orders.
     */
    public function run(): void
    {
        $store = Store::first();
        $user = User::where('role', 'manager')->first();

        // Get all received purchase orders
        $receivedPOs = PurchaseOrder::where('status', 'received')
            ->with('supplier')
            ->orderBy('received_date')
            ->get();

        $invoiceCount = 0;
        $paymentCount = 0;

        foreach ($receivedPOs as $po) {
            $supplier = $po->supplier;

            // Create AP invoice for this received PO
            $invoice = PayableTransaction::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $po->id,
                'user_id' => $user->id,
                'type' => 'invoice',
                'reference_number' => $po->po_number,
                'amount' => $po->total_amount,
                'balance_before' => $supplier->total_outstanding,
                'balance_after' => $supplier->total_outstanding + $po->total_amount,
                'description' => "AP Invoice for {$po->po_number}",
                'transaction_date' => $po->received_date,
                'due_date' => $po->payment_due_date,
                'paid_date' => $po->payment_completed_date,
                'payment_method' => null,
                'notes' => "Automatically created from PO receipt",
                'is_reversed' => false,
                'created_at' => $po->received_date,
                'updated_at' => $po->updated_at,
            ]);

            $invoiceCount++;

            // Update supplier's total outstanding
            $supplier->total_outstanding += $po->total_amount;
            $supplier->total_purchases += $po->total_amount;
            $supplier->save();

            // If PO has payments (partial or paid), create payment transactions
            if ($po->amount_paid > 0) {
                $paymentMethods = ['bank_transfer', 'check', 'cash'];
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

                // Determine payment date
                if ($po->payment_completed_date) {
                    $paymentDate = $po->payment_completed_date;
                } else {
                    // Partial payment - use a date between received date and due date
                    $receivedTime = $po->received_date->timestamp;
                    $dueTime = $po->payment_due_date->timestamp;
                    $randomTime = rand($receivedTime, $dueTime);
                    $paymentDate = \Carbon\Carbon::createFromTimestamp($randomTime);
                }

                // Create payment transaction
                PayableTransaction::create([
                    'uuid' => Str::uuid(),
                    'store_id' => $store->id,
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $po->id,
                    'user_id' => $user->id,
                    'type' => 'payment',
                    'reference_number' => 'PMT-' . strtoupper(substr(md5($po->uuid), 0, 8)),
                    'amount' => -$po->amount_paid, // Negative for payment
                    'balance_before' => $supplier->total_outstanding,
                    'balance_after' => $supplier->total_outstanding - $po->amount_paid,
                    'description' => "Payment for {$po->po_number}",
                    'transaction_date' => $paymentDate,
                    'due_date' => null,
                    'paid_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'notes' => $po->payment_status === 'paid' ? 'Full payment' : 'Partial payment',
                    'is_reversed' => false,
                    'created_at' => $paymentDate,
                    'updated_at' => $paymentDate,
                ]);

                $paymentCount++;

                // Update supplier's outstanding balance
                $supplier->total_outstanding -= $po->amount_paid;
                $supplier->save();
            }
        }

        // Update payment rating based on outstanding balances
        $suppliers = Supplier::where('store_id', $store->id)->get();
        foreach ($suppliers as $supplier) {
            if ($supplier->total_outstanding == 0 || $supplier->total_purchases == 0) {
                $supplier->payment_rating = 'excellent';
            } else {
                $ratio = $supplier->total_outstanding / $supplier->total_purchases;
                if ($ratio < 0.1) {
                    $supplier->payment_rating = 'excellent';
                } elseif ($ratio < 0.3) {
                    $supplier->payment_rating = 'good';
                } elseif ($ratio < 0.6) {
                    $supplier->payment_rating = 'fair';
                } else {
                    $supplier->payment_rating = 'poor';
                }
            }
            $supplier->save();
        }

        echo "Created {$invoiceCount} AP invoices and {$paymentCount} payments.\n";
        echo "Updated supplier outstanding balances and payment ratings.\n";
    }
}
