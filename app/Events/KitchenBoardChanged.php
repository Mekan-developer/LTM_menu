<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Экран кухни/бара должен обновиться (новый заказ, «Готово», удаление позиции). */
class KitchenBoardChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param list<string> $stations */
    public function __construct(public array $stations) {}

    public function broadcastOn(): array
    {
        return array_map(fn (string $s) => new PrivateChannel('kitchen.'.$s), array_values(array_unique($this->stations)));
    }

    public function broadcastAs(): string
    {
        return 'kitchen.changed';
    }

    public function broadcastWith(): array
    {
        return ['stations' => $this->stations];
    }
}
