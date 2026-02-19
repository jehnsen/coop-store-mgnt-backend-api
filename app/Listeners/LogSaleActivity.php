<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Events\SaleVoided;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;

class LogSaleActivity implements ShouldQueue
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
     * Handle SaleCompleted event.
     */
    public function handleSaleCompleted(SaleCompleted $event): void
    {
        $sale = $event->sale;

        ActivityLog::create([
            'store_id' => $sale->store_id,
            'user_id' => $sale->user_id,
            'action' => 'sale_created',
            'description' => sprintf(
                'Sale #%s created for ₱%s',
                $sale->sale_number,
                number_format($sale->total_amount / 100, 2)
            ),
            'subject_type' => get_class($sale),
            'subject_id' => $sale->id,
            'properties' => [
                'sale_number' => $sale->sale_number,
                'total_amount' => $sale->total_amount,
                'customer_id' => $sale->customer_id,
                'items_count' => $sale->items->count(),
                'payment_methods' => $sale->payments->pluck('method')->toArray(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Handle SaleVoided event.
     */
    public function handleSaleVoided(SaleVoided $event): void
    {
        $sale = $event->sale;

        ActivityLog::create([
            'store_id' => $sale->store_id,
            'user_id' => $sale->voided_by,
            'action' => 'sale_voided',
            'description' => sprintf(
                'Sale #%s voided. Reason: %s',
                $sale->sale_number,
                $event->reason
            ),
            'subject_type' => get_class($sale),
            'subject_id' => $sale->id,
            'properties' => [
                'sale_number' => $sale->sale_number,
                'total_amount' => $sale->total_amount,
                'void_reason' => $event->reason,
                'original_sale_date' => $sale->sale_date->toDateTimeString(),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            SaleCompleted::class => 'handleSaleCompleted',
            SaleVoided::class => 'handleSaleVoided',
        ];
    }
}
