<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateCustomerTotals implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;

        // Update customer totals if customer exists
        if ($sale->customer) {
            // Total purchases is already updated in SaleService
            // But we can update last purchase date here if needed
            $sale->customer->update([
                'last_purchase_date' => $sale->sale_date,
            ]);

            // Update customer tier based on total purchases if applicable
            $this->updateCustomerTier($sale->customer);
        }
    }

    /**
     * Update customer tier based on total purchases.
     */
    protected function updateCustomerTier($customer): void
    {
        // Example tier logic (customize based on business rules)
        $totalPurchases = $customer->total_purchases / 100; // Convert to pesos

        if ($totalPurchases >= 1000000) { // 1M+
            $newTier = 'platinum';
        } elseif ($totalPurchases >= 500000) { // 500K+
            $newTier = 'gold';
        } elseif ($totalPurchases >= 100000) { // 100K+
            $newTier = 'silver';
        } else {
            $newTier = 'bronze';
        }

        // Only update if tier changed
        if ($customer->customer_tier !== $newTier) {
            $customer->update(['customer_tier' => $newTier]);
        }
    }
}
