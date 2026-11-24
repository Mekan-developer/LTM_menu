<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Support\Money;

/**
 * Пересчёт суммы. В зале в счёт идут только отправленные на кухню позиции.
 * Доставка и самовывоз — весь заказ сразу (гость видит сумму при оформлении) плюс стоимость доставки;
 * скидка на доставку не действует.
 */
class OrderCalculator
{
    public function recalculate(Order $order): Order
    {
        $statuses = $order->isHall()
            ? OrderItemStatus::billable()
            : [OrderItemStatus::Draft->value, ...OrderItemStatus::billable()];

        $subtotal = (int) $order->items()
            ->whereIn('status', $statuses)
            ->selectRaw('coalesce(sum(price * quantity), 0) as s')
            ->value('s');

        $discount = Money::percentOf($subtotal, $order->discount_percent);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => max(0, $subtotal - $discount) + $order->delivery_fee,
        ])->save();

        return $order;
    }
}
