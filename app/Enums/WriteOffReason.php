<?php

namespace App\Enums;

/** Причина ручного списания (ТЗ 2.3): порча, бой, питание персонала, другое (с комментарием). */
enum WriteOffReason: string
{
    case Spoiled = 'spoiled';
    case Broken = 'broken';
    case Staff = 'staff';
    case Other = 'other';
}
