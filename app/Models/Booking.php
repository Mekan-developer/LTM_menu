<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Бронь стола (ТЗ 2.2). */
class Booking extends Model
{
    protected $fillable = [
        'guest_id', 'dining_table_id', 'starts_at', 'ends_at', 'guests_count', 'status', 'source',
        'comment', 'cancel_reason', 'order_id', 'created_by', 'confirmed_at', 'arrived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'guests_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<BookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
