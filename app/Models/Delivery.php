<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Доставка или самовывоз заказа (ТЗ 2.6). */
class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'status', 'customer_name', 'phone', 'address', 'planned_method', 'courier_id', 'created_by',
        'accepted_at', 'cooking_at', 'ready_at', 'picked_at', 'finished_at',
        'cancelled_at', 'cancelled_by', 'cancel_reason', 'cash_received_at', 'cash_received_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'planned_method' => PaymentMethod::class,
            'accepted_at' => 'datetime',
            'cooking_at' => 'datetime',
            'ready_at' => 'datetime',
            'picked_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cash_received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
