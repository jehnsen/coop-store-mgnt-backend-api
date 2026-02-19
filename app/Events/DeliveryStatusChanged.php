<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Delivery $delivery;
    public string $oldStatus;
    public string $newStatus;

    /**
     * Create a new event instance.
     */
    public function __construct(Delivery $delivery, string $oldStatus, string $newStatus)
    {
        $this->delivery = $delivery;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.' . $this->delivery->store_id),
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
            'delivery_id' => $this->delivery->uuid,
            'delivery_number' => $this->delivery->delivery_number,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'customer_name' => $this->delivery->customer->name ?? 'Walk-in Customer',
            'dispatched_at' => $this->delivery->dispatched_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivery->delivered_at?->format('Y-m-d H:i:s'),
            'failed_at' => $this->delivery->failed_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->delivery->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'delivery.status.changed';
    }
}
