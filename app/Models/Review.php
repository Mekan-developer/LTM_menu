<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    /** Оценка 3 и ниже — уведомление пользователям с правом «Отзывы» (ТЗ 2.9). */
    public const LOW_RATING = 3;

    protected $fillable = ['order_id', 'waiter_id', 'token', 'rating', 'comment', 'submitted_at', 'seen_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'submitted_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isLow(): bool
    {
        return $this->rating !== null && $this->rating <= self::LOW_RATING;
    }
}
