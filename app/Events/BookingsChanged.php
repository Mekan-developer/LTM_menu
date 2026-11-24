<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Изменился список броней — экран «Брони» перезагружается (новая бронь от гостя, статус). */
class BookingsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $bookingId, public string $date) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('bookings')];
    }

    public function broadcastAs(): string
    {
        return 'bookings.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['booking_id' => $this->bookingId, 'date' => $this->date];
    }
}
