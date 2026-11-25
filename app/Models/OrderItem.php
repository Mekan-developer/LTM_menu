<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use App\Enums\Station;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasLocalizedName;

    protected $fillable = [
        'order_id', 'dish_id', 'name_tm', 'name_ru', 'price', 'quantity', 'station', 'status',
        'comment', 'batch', 'added_by', 'sent_at', 'ready_at', 'served_at', 'removed_reason', 'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'batch' => 'integer',
            'station' => Station::class,
            'status' => OrderItemStatus::class,
            'sent_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Dish, $this> */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** @return BelongsTo<User, $this> */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function lineTotal(): int
    {
        return $this->price * $this->quantity;
    }
}
