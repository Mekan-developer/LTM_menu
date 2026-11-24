<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'public_token', 'dining_table_id', 'waiter_id', 'source', 'status', 'guest_comment',
        'discount_percent', 'subtotal', 'discount_amount', 'total', 'batch_counter',
        'opened_at', 'bill_requested_at', 'paid_at', 'closed_at',
    ];

    /** Совпадают с DEFAULT в миграции: после create() суммы и счётчик уже не null. */
    protected $attributes = [
        'discount_percent' => 0,
        'subtotal' => 0,
        'discount_amount' => 0,
        'total' => 0,
        'batch_counter' => 0,
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'discount_percent' => 'integer',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
            'batch_counter' => 'integer',
            'opened_at' => 'datetime',
            'bill_requested_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function activePayment(): HasOne
    {
        return $this->hasOne(Payment::class)->whereNull('cancelled_at')->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function hasUnsentItems(): bool
    {
        return $this->items()
            ->whereIn('status', [OrderItemStatus::Draft->value, OrderItemStatus::Pending->value])
            ->exists();
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
