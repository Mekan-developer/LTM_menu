<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Оценка 3 и ниже — уведомление в админке пользователям с правом «Отзывы» (ТЗ 2.9). */
class LowReviewReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $reviewId,
        public int $rating,
        public ?string $table,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('reviews')];
    }

    public function broadcastAs(): string
    {
        return 'review.low';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['review_id' => $this->reviewId, 'rating' => $this->rating, 'table' => $this->table];
    }
}
