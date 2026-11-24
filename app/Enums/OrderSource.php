<?php

namespace App\Enums;

enum OrderSource: string
{
    case Waiter = 'waiter';
    case Qr = 'qr';
    case Booking = 'booking'; // заказ открыт по брони, когда гость пришёл (ТЗ 2.2)
    case Online = 'online';   // доставка / самовывоз со страницы для гостей (ТЗ 2.6)
    case Phone = 'phone';     // доставка / самовывоз, принятые по звонку
}
