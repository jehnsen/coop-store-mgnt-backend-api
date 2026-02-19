<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleVoided implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Sale $sale;
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Sale $sale, string $reason)
    {
        $this->sale = $sale;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.' . $this->sale->store_id),
            new PrivateChannel('branch.' . $this->sale->branch_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sale.voided';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'sale_id' => $this->sale->uuid,
            'sale_number' => $this->sale->sale_number,
            'reason' => $this->reason,
            'voided_by' => $this->sale->voidedBy->name ?? 'Unknown',
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
