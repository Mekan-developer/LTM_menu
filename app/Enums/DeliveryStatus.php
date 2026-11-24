<?php

namespace App\Enums;

/**
 * Статусы доставки и самовывоза (ТЗ 2.6):
 * новый → принят → готовится → готов → у курьера → доставлен / выдан; или отменён.
 * «Готовится» ставится при отправке на кухню, «готов» — когда кухня нажмёт «Готово».
 */
enum DeliveryStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case OnWay = 'on_way';
    case Delivered = 'delivered';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return in_array($this, [self::Delivered, self::Issued, self::Cancelled], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => ! $s->isFinished()));
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::active());
    }
}
