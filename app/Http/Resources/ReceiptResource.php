<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array formatted for thermal printer (80mm paper).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $store = $this->user->store;
        $branch = $this->branch;

        return [
            // Store information
            'store' => [
                'name' => $store->name,
                'address' => $branch->address ?? $store->address,
                'phone' => $branch->phone ?? $store->phone,
                'email' => $store->email,
                'tin' => $store->tin,
                'bir_permit' => $store->bir_permit_number,
                'website' => $store->website,
            ],

            // Sale information
            'sale' => [
                'sale_number' => $this->sale_number,
                'date' => $this->sale_date->format('M d, Y h:i A'),
                'cashier' => $this->user->name,
                'branch' => $branch->name ?? 'Main Branch',
            ],

            // Customer information (if exists)
            'customer' => $this->customer ? [
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
                'address' => $this->customer->address,
            ] : null,

            // Line items
            'items' => $this->items->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => number_format($item->quantity, 2),
                    'unit_of_measure' => $item->product->unit_of_measure,
                    'unit_price' => '₱' . number_format($item->unit_price / 100, 2),
                    'discount' => $item->discount_amount > 0 ? [
                        'type' => $item->discount_type,
                        'value' => $item->discount_value,
                        'amount' => '₱' . number_format($item->discount_amount / 100, 2),
                    ] : null,
                    'line_total' => '₱' . number_format($item->line_total / 100, 2),
                ];
            })->toArray(),

            // Totals breakdown
            'totals' => [
                'subtotal' => '₱' . number_format($this->subtotal / 100, 2),
                'discount' => $this->discount_amount > 0 ? [
                    'type' => $this->discount_type,
                    'value' => $this->discount_value,
                    'amount' => '₱' . number_format($this->discount_amount / 100, 2),
                ] : null,
                'vat_rate' => $store->vat_rate . '%',
                'vat_type' => $store->vat_inclusive ? 'VAT Inclusive' : 'VAT Exclusive',
                'vat_amount' => '₱' . number_format($this->vat_amount / 100, 2),
                'vat_sales' => '₱' . number_format(($this->total_amount - $this->vat_amount) / 100, 2),
                'total' => '₱' . number_format($this->total_amount / 100, 2),
            ],

            // Payments breakdown
            'payments' => $this->payments->map(function ($payment) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $payment->method)),
                    'amount' => '₱' . number_format($payment->amount / 100, 2),
                    'reference' => $payment->reference_number,
                ];
            })->toArray(),

            // Calculate change if cash payment
            'change' => $this->calculateChange(),

            // Additional info
            'notes' => $this->notes,

            // Footer
            'footer' => [
                'message' => $store->receipt_footer ?? 'Thank you for your business!',
                'terms' => 'All sales are final unless otherwise stated.',
                'powered_by' => 'Powered by CloudPOS',
            ],

            // For reprints
            'is_reprint' => $request->has('reprint'),
            'printed_at' => now()->format('M d, Y h:i A'),
        ];
    }

    /**
     * Calculate change given if cash payment exists.
     */
    protected function calculateChange(): ?string
    {
        $cashPayment = $this->payments->firstWhere('method', 'cash');

        if (!$cashPayment) {
            return null;
        }

        $change = $cashPayment->amount - $this->total_amount;

        return $change > 0 ? '₱' . number_format($change / 100, 2) : null;
    }
}
