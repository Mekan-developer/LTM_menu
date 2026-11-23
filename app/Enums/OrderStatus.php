<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case BillRequested = 'bill_requested';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** Заказ занимает стол. */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::BillRequested], true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Open->value, self::BillRequested->value];
    }
}
