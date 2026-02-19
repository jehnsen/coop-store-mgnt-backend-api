<?php

namespace App\Listeners;

use App\Events\PurchaseOrderReceived;
use App\Services\PayableService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateAPInvoiceForReceivedPO implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected PayableService $payableService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(PurchaseOrderReceived $event): void
    {
        $purchaseOrder = $event->purchaseOrder;

        // Only create invoice if PO is fully received and not already invoiced
        if ($purchaseOrder->status !== 'received') {
            Log::info("Skipping AP invoice creation: PO {$purchaseOrder->po_number} is not fully received (status: {$purchaseOrder->status})");
            return;
        }

        // Check if invoice already created (avoid duplicates)
        if ($purchaseOrder->payableTransactions()->where('type', 'invoice')->exists()) {
            Log::info("Skipping AP invoice creation: PO {$purchaseOrder->po_number} already has an invoice");
            return;
        }

        try {
            // Get supplier with fresh data
            $supplier = $purchaseOrder->supplier;

            // Get total amount and payment terms
            $totalAmountCentavos = $purchaseOrder->getRawOriginal('total_amount');
            $paymentTerms = $supplier->payment_terms_days ?? 30;

            // Create AP invoice
            $invoice = $this->payableService->createInvoice(
                $supplier,
                $purchaseOrder,
                $totalAmountCentavos,
                $paymentTerms
            );

            Log::info("AP invoice created successfully for PO {$purchaseOrder->po_number}", [
                'invoice_id' => $invoice->id,
                'supplier_id' => $supplier->id,
                'amount' => $totalAmountCentavos / 100,
                'due_date' => $invoice->due_date?->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create AP invoice for PO {$purchaseOrder->po_number}: " . $e->getMessage(), [
                'exception' => $e,
                'purchase_order_id' => $purchaseOrder->id,
            ]);

            // Re-throw to let queue retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PurchaseOrderReceived $event, \Throwable $exception): void
    {
        Log::error("Failed to create AP invoice after retries for PO {$event->purchaseOrder->po_number}", [
            'purchase_order_id' => $event->purchaseOrder->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
