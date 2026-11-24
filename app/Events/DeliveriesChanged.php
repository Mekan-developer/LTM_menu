<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Изменился заказ на доставку или самовывоз (ТЗ 2.6): экраны «Доставка» и курьера перезагружаются.
 * courierId — кому из курьеров это важно (назначен или снят), чтобы его телефон подал сигнал.
 */
class DeliveriesChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $orderId, public string $status, public ?int $courierId = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('deliveries')];
    }

    public function broadcastAs(): string
    {
        return 'deliveries.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'status' => $this->status, 'courier_id' => $this->courierId];
    }
}
