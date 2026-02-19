<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Sale;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeliveryService
{
    protected DeliveryRepositoryInterface $deliveryRepository;
    protected SaleRepositoryInterface $saleRepository;

    public function __construct(
        DeliveryRepositoryInterface $deliveryRepository,
        SaleRepositoryInterface $saleRepository
    ) {
        $this->deliveryRepository = $deliveryRepository;
        $this->saleRepository = $saleRepository;
    }
    /**
     * Create delivery from sale.
     */
    public function createDelivery(Sale $sale, array $data): Delivery
    {
        return DB::transaction(function () use ($sale, $data) {
            $deliveryNumber = $this->generateDeliveryNumber();

            $delivery = $this->deliveryRepository->create([
                'uuid' => Str::uuid(),
                'store_id' => $sale->store_id,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'delivery_number' => $deliveryNumber,
                'status' => 'preparing',
                'scheduled_date' => $data['scheduled_date'],
                'delivery_address' => $data['delivery_address'],
                'delivery_city' => $data['delivery_city'] ?? null,
                'delivery_province' => $data['delivery_province'] ?? null,
                'delivery_postal_code' => $data['delivery_postal_code'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'contact_phone' => $data['contact_phone'],
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $saleItem = $sale->items()->find($item['sale_item_id']);
                    if ($saleItem) {
                        DeliveryItem::create([
                            'delivery_id' => $delivery->id,
                            'product_id' => $saleItem->product_id,
                            'sale_item_id' => $saleItem->id,
                            'quantity' => $item['quantity'],
                        ]);
                    }
                }
            } else {
                foreach ($sale->items as $saleItem) {
                    DeliveryItem::create([
                        'delivery_id' => $delivery->id,
                        'product_id' => $saleItem->product_id,
                        'sale_item_id' => $saleItem->id,
                        'quantity' => $saleItem->quantity,
                    ]);
                }
            }

            $sale->update(['delivery_status' => 'preparing']);
            return $delivery->load(['sale', 'customer', 'items.product']);
        });
    }

    public function updateStatus(Delivery $delivery, string $status, ?string $notes = null, array $extraData = []): Delivery
    {
        $this->validateStatusTransition($delivery->status, $status);

        return DB::transaction(function () use ($delivery, $status, $notes, $extraData) {
            $updateData = ['status' => $status];

            switch ($status) {
                case 'dispatched':
                    $updateData['dispatched_at'] = now();
                    break;
                case 'delivered':
                    $updateData['delivered_at'] = $extraData['delivered_at'] ?? now();
                    $updateData['received_by'] = $extraData['received_by'] ?? null;
                    $delivery->sale->update(['delivery_status' => 'delivered']);
                    break;
                case 'failed':
                    $updateData['failed_at'] = now();
                    $updateData['failed_reason'] = $extraData['failed_reason'] ?? $notes;
                    $delivery->sale->update(['delivery_status' => 'failed']);
                    break;
            }

            if ($notes) $updateData['notes'] = $notes;
            $delivery->update($updateData);

            return $delivery->fresh();
        });
    }

    public function generateDeliveryNumber(): string
    {
        $year = now()->year;
        $prefix = "DEL-{$year}-";

        // Direct query for number generation with locking
        // Repository doesn't have getNextDeliveryNumber() method yet
        $lastDelivery = Delivery::where('delivery_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $newNumber = $lastDelivery ? ((int) substr($lastDelivery->delivery_number, -6)) + 1 : 1;
        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    protected function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $validTransitions = [
            'preparing' => ['dispatched', 'failed'],
            'dispatched' => ['in_transit', 'delivered', 'failed'],
            'in_transit' => ['delivered', 'failed'],
            'delivered' => [],
            'failed' => [],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }
    }
}
