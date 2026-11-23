<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Очередь кассы изменилась: попросили счёт, оплатили, отменили. */
class CashierQueueChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $orderId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('cashier')];
    }

    public function broadcastAs(): string
    {
        return 'cashier.changed';
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId];
    }
}
