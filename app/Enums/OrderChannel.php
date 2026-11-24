<?php

namespace App\Enums;

/** Канал заказа (ТЗ 2.6, 2.10): зал, доставка или самовывоз. */
enum OrderChannel: string
{
    case Hall = 'hall';
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
