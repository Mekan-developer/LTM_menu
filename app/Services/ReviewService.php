<?php

namespace App\Services;

use App\Events\LowReviewReceived;
use App\Models\Order;
use App\Models\Review;
use App\Repositories\ReviewRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Отзывы (ТЗ 2.9). Одноразовая ссылка создаётся при оплате счёта.
 * Пока нет push/SMS (ТЗ 3.2), гость получает ссылку на странице статуса заказа и QR-кодом на чеке.
 */
class ReviewService
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly SettingsService $settings,
        private readonly Realtime $realtime,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('reviews_enabled');
    }

    /** Ссылка на оценку для оплаченного счёта. Выключено в настройках — ссылки нет. */
    public function createForOrder(Order $order): ?Review
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->reviews->firstOrCreateForOrder($order->id, [
            'waiter_id' => $order->waiter_id,
            'token' => Str::lower(Str::random(32)),
        ]);
    }

    /** Ссылка, по которой гость ещё может оценить счёт; иначе null. */
    public function pendingUrl(Order $order): ?string
    {
        $review = $order->review;

        if (! $this->enabled() || $review === null || $review->isSubmitted()) {
            return null;
        }

        return route('guest.review', $review->token);
    }

    public function submit(Review $review, int $rating, ?string $comment): void
    {
        if ($review->isSubmitted()) {
            throw ValidationException::withMessages(['rating' => __('crm.reviews.already')]);
        }

        $review->forceFill([
            'rating' => $rating,
            'comment' => filled($comment) ? trim($comment) : null,
            'submitted_at' => now(),
        ])->save();

        if ($review->isLow()) {
            $review->loadMissing('order.table');
            $this->realtime->send(new LowReviewReceived($review->id, $rating, $review->order?->table?->number));
        }
    }
}
