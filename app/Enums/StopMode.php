<?php

namespace App\Enums;

/** Стоп-меню: до конца дня, до указанного времени или пока не снимут вручную. */
enum StopMode: string
{
    case EndOfDay = 'end_of_day';
    case UntilTime = 'until_time';
    case Manual = 'manual';
}
