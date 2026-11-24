<?php

namespace App\Enums;

enum OrderSource: string
{
    case Waiter = 'waiter';
    case Qr = 'qr';
    case Booking = 'booking'; // заказ открыт по брони, когда гость пришёл (ТЗ 2.2)
}
