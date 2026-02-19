<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'reference_number' => $this->reference_number,
            'payment_method' => $this->payment_method,
            'description' => $this->description,
            'notes' => $this->notes,

            // Dates
            'transaction_date' => $this->transaction_date?->toISOString(),
            'due_date' => $this->due_date?->toDateString(),
            'paid_date' => $this->paid_date?->toDateString(),

            // Status computation
            'status' => $this->getStatus(),

            // Days overdue if applicable
            'days_overdue' => $this->getDaysOverdue(),

            // Purchase Order information
            'purchase_order_id' => $this->purchase_order_id,
            'po_number' => $this->whenLoaded('purchaseOrder', function () {
                return $this->purchaseOrder->po_number ?? null;
            }),

            // Relationships
            'purchase_order' => $this->whenLoaded('purchaseOrder', function () {
                return [
                    'id' => $this->purchaseOrder->id,
                    'uuid' => $this->purchaseOrder->uuid,
                    'po_number' => $this->purchaseOrder->po_number,
                    'order_date' => $this->purchaseOrder->order_date?->toDateString(),
                    'total_amount' => $this->purchaseOrder->total_amount / 100,
                    'payment_status' => $this->purchaseOrder->payment_status,
                ];
            }),

            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'uuid' => $this->supplier->uuid,
                    'name' => $this->supplier->name,
                    'phone' => $this->supplier->phone,
                ];
            }),

            // Reversal info
            'is_reversed' => (bool) $this->is_reversed,
            'reversed_at' => $this->reversed_at?->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Determine the status of the transaction.
     *
     * @return string
     */
    private function getStatus(): string
    {
        if ($this->is_reversed) {
            return 'reversed';
        }

        if ($this->type === 'payment') {
            return 'paid';
        }

        if ($this->paid_date) {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        // Check if partially paid by looking at balance
        if ($this->type === 'invoice') {
            $originalAmount = $this->getRawOriginal('amount');
            $currentBalance = $this->getRawOriginal('balance_after');

            if ($currentBalance < $originalAmount && $currentBalance > 0) {
                return 'partially_paid';
            }
        }

        return 'outstanding';
    }

    /**
     * Calculate days overdue.
     *
     * @return int|null
     */
    private function getDaysOverdue(): ?int
    {
        if (!$this->due_date || !$this->due_date->isPast() || $this->paid_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }
}
