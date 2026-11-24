<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Изменился статус стола или схема зала — экраны схемы перезагружают доску. */
class HallChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $hallId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('hall')];
    }

    public function broadcastAs(): string
    {
        return 'hall.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['hall_id' => $this->hallId];
    }
}
