<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Support\Money;

/** Пересчёт суммы: в счёт идут только отправленные на кухню позиции. */
class OrderCalculator
{
    public function recalculate(Order $order): Order
    {
        $subtotal = (int) $order->items()
            ->whereIn('status', OrderItemStatus::billable())
            ->selectRaw('coalesce(sum(price * quantity), 0) as s')
            ->value('s');

        $discount = Money::percentOf($subtotal, $order->discount_percent);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => max(0, $subtotal - $discount),
        ])->save();

        return $order;
    }
}
