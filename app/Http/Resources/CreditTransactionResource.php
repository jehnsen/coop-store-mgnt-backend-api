<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditTransactionResource extends JsonResource
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

            // Sale information
            'sale_id' => $this->sale_id,
            'sale_number' => $this->whenLoaded('sale', function () {
                return $this->sale->sale_number ?? null;
            }),

            // Relationships
            'sale' => $this->whenLoaded('sale', function () {
                return [
                    'id' => $this->sale->id,
                    'uuid' => $this->sale->uuid,
                    'sale_number' => $this->sale->sale_number,
                    'sale_date' => $this->sale->sale_date?->toDateString(),
                    'total_amount' => $this->sale->total_amount / 100,
                    'payment_status' => $this->sale->payment_status,
                ];
            }),

            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'uuid' => $this->customer->uuid,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
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
        if ($this->type === 'charge') {
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
