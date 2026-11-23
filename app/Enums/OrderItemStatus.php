<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Draft = 'draft';       // официант добавил, ещё не отправил
    case Pending = 'pending';   // QR-заказ ждёт подтверждения официанта
    case Sent = 'sent';         // на кухне
    case Ready = 'ready';       // кухня нажала «Готово»
    case Served = 'served';     // подано
    case Removed = 'removed';   // удалено (с причиной)

    /**
     * Позиции, которые попадают в счёт.
     *
     * @return list<string>
     */
    public static function billable(): array
    {
        return [self::Sent->value, self::Ready->value, self::Served->value];
    }

    /**
     * Позиции, которые видит гость в статусе заказа.
     *
     * @return list<string>
     */
    public static function visibleToGuest(): array
    {
        return [self::Pending->value, self::Sent->value, self::Ready->value, self::Served->value];
    }
}
