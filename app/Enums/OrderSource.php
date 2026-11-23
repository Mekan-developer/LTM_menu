<?php

namespace App\Enums;

enum OrderSource: string
{
    case Waiter = 'waiter';
    case Qr = 'qr';
}
