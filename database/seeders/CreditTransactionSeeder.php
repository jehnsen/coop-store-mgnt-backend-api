<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\User;
use App\Models\CreditTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CreditTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = Store::first();
        $cashiers = User::whereIn('role', ['cashier', 'manager', 'owner'])->get();

        // Get all credit sales (unpaid)
        $creditSales = Sale::where('payment_status', 'unpaid')->get();

        echo "Processing {$creditSales->count()} credit sales...\n";

        foreach ($creditSales as $sale) {
            $customer = Customer::find($sale->customer_id);

            // Create credit charge transaction
            $dueDate = Carbon::parse($sale->created_at)->addDays($customer->credit_terms_days);

            CreditTransaction::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'user_id' => $sale->user_id,
                'type' => 'charge',
                'reference_number' => $sale->sale_number,
                'amount' => $sale->total_amount, // Positive for charges
                'balance_before' => 0, // Will update properly below
                'balance_after' => 0, // Will update properly below
                'due_date' => $dueDate,
                'paid_date' => null,
                'payment_method' => null,
                'notes' => "Credit sale: {$sale->sale_number}",
                'created_at' => $sale->created_at,
                'updated_at' => $sale->created_at,
            ]);
        }

        // Now update customer balances and transaction balances
        $customers = Customer::where('allow_credit', true)->get();

        foreach ($customers as $customer) {
            $transactions = CreditTransaction::where('customer_id', $customer->id)
                ->orderBy('created_at')
                ->get();

            $runningBalance = 0;

            foreach ($transactions as $transaction) {
                $transaction->balance_before = $runningBalance;

                if ($transaction->type === 'charge') {
                    $runningBalance += $transaction->amount;
                } elseif ($transaction->type === 'payment') {
                    $runningBalance -= abs($transaction->amount);
                }

                $transaction->balance_after = $runningBalance;
                $transaction->save();
            }
        }

        // Create payments for 60-90% of credit sales
        $creditSalesWithPayments = $creditSales->shuffle()->take((int) ($creditSales->count() * 0.75));

        foreach ($creditSalesWithPayments as $sale) {
            $customer = Customer::find($sale->customer_id);
            $saleDate = Carbon::parse($sale->created_at);
            $dueDate = $saleDate->copy()->addDays($customer->credit_terms_days);

            // Random payment: 70% paid on time, 20% paid early, 10% paid late
            $paymentTimingRand = rand(1, 100);

            if ($paymentTimingRand <= 20) {
                // Paid early (5-15 days before due)
                $paymentDate = $dueDate->copy()->subDays(rand(5, 15));
            } elseif ($paymentTimingRand <= 90) {
                // Paid on time (at due date or 1-3 days after)
                $paymentDate = $dueDate->copy()->addDays(rand(0, 3));
            } else {
                // Paid late (5-20 days after due)
                $paymentDate = $dueDate->copy()->addDays(rand(5, 20));
            }

            // Make sure payment is not in the future
            if ($paymentDate->isFuture()) {
                $paymentDate = Carbon::now()->subDays(rand(1, 5));
            }

            // Determine payment method for credit payment
            $paymentMethodRand = rand(1, 100);
            if ($paymentMethodRand <= 50) {
                $paymentMethod = 'cash';
                $refNumber = null;
            } elseif ($paymentMethodRand <= 75) {
                $paymentMethod = 'bank_transfer';
                $refNumber = 'BANK-' . rand(100000, 999999);
            } elseif ($paymentMethodRand <= 90) {
                $paymentMethod = 'check';
                $refNumber = 'CHK-' . rand(1000000, 9999999);
            } else {
                $paymentMethod = 'gcash';
                $refNumber = 'GCASH-' . rand(100000000, 999999999);
            }

            // Create payment transaction (80% full payment, 20% partial)
            $isPartialPayment = rand(1, 100) <= 20;

            if ($isPartialPayment) {
                // Partial payment (50-90% of total)
                $paymentAmount = (int) ($sale->total_amount * (rand(50, 90) / 100));
                $sale->update([
                    'payment_status' => 'partial',
                    'amount_paid' => $paymentAmount,
                ]);
            } else {
                // Full payment
                $paymentAmount = $sale->total_amount;
                $sale->update([
                    'payment_status' => 'paid',
                    'amount_paid' => $paymentAmount,
                ]);
            }

            $cashier = $cashiers->random();

            CreditTransaction::create([
                'uuid' => Str::uuid(),
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'user_id' => $cashier->id,
                'type' => 'payment',
                'reference_number' => $refNumber,
                'amount' => -$paymentAmount, // Negative for payments
                'balance_before' => 0, // Will update below
                'balance_after' => 0, // Will update below
                'due_date' => null,
                'paid_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'notes' => $isPartialPayment ? "Partial payment for {$sale->sale_number}" : "Full payment for {$sale->sale_number}",
                'created_at' => $paymentDate,
                'updated_at' => $paymentDate,
            ]);

            // Create sale_payment record
            \DB::table('sale_payments')->insert([
                'sale_id' => $sale->id,
                'method' => $paymentMethod,
                'amount' => $paymentAmount,
                'reference_number' => $refNumber,
                'notes' => null,
                'created_at' => $paymentDate,
                'updated_at' => $paymentDate,
            ]);
        }

        // Recalculate all customer balances with payments
        foreach ($customers as $customer) {
            $transactions = CreditTransaction::where('customer_id', $customer->id)
                ->orderBy('created_at')
                ->get();

            $runningBalance = 0;

            foreach ($transactions as $transaction) {
                $transaction->balance_before = $runningBalance;

                if ($transaction->type === 'charge') {
                    $runningBalance += $transaction->amount;
                } elseif ($transaction->type === 'payment') {
                    $runningBalance += $transaction->amount; // Already negative
                }

                $transaction->balance_after = $runningBalance;
                $transaction->save();
            }

            // Update customer total_outstanding
            $customer->update([
                'total_outstanding' => max(0, $runningBalance),
            ]);
        }

        echo "Credit transactions created and customer balances updated.\n";
    }
}
