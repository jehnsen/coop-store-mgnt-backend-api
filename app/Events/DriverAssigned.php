<?php

namespace App\Events;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Delivery $delivery;
    public User $driver;

    /**
     * Create a new event instance.
     */
    public function __construct(Delivery $delivery, User $driver)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
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
            new PrivateChannel('user.' . $this->driver->id),
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
            'status' => $this->delivery->status,
            'scheduled_date' => $this->delivery->scheduled_date?->format('Y-m-d H:i:s'),
            'delivery_address' => $this->delivery->delivery_address,
            'customer_name' => $this->delivery->customer->name ?? 'Walk-in Customer',
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'email' => $this->driver->email,
                'phone' => $this->driver->phone,
            ],
            'assigned_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'driver.assigned';
    }
}
