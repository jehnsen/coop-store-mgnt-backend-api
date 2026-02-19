<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PurchaseOrder $purchaseOrder;

    /**
     * Create a new event instance.
     */
    public function __construct(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.' . $this->purchaseOrder->store_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_uuid' => $this->purchaseOrder->uuid,
            'po_number' => $this->purchaseOrder->po_number,
            'status' => $this->purchaseOrder->status,
            'supplier_name' => $this->purchaseOrder->supplier->name,
            'total_amount' => $this->purchaseOrder->total_amount,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'purchase-order.received';
    }
}
