<?php

namespace App\Enums;

/** Статус стола на схеме зала (ТЗ 2.2). Считается на сервере. */
enum TableStatus: string
{
    case Free = 'free';
    case Occupied = 'occupied';
    case Bill = 'bill';
    case Reserved = 'reserved'; // появится вместе с бронями
}
