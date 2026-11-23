<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Сигнал официантам: новый QR-заказ (type=qr_order) или блюдо готово (type=ready).
 * Клиент сам решает, показывать ли: свой стол, неназначенный QR или право видеть все столы.
 */
class WaiterAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $type,
        public int $orderId,
        public ?string $tableNumber,
        public ?int $waiterId,
        public ?int $batch = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('waiters')];
    }

    public function broadcastAs(): string
    {
        return 'waiter.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'order_id' => $this->orderId,
            'table' => $this->tableNumber,
            'waiter_id' => $this->waiterId,
            'batch' => $this->batch,
        ];
    }
}
