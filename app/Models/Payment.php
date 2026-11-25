<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'cashier_id', 'method', 'cash_amount', 'card_amount', 'bonus_amount', 'bonus_accrued', 'total',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $attributes = ['bonus_amount' => 0, 'bonus_accrued' => 0];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'cash_amount' => 'integer',
            'card_amount' => 'integer',
            'bonus_amount' => 'integer',
            'bonus_accrued' => 'integer',
            'total' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return HasMany<Tip, $this> */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }
}
