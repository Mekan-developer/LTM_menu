<?php

namespace App\Enums;

/** Неинтерактивные объекты на схеме зала. */
enum HallElementType: string
{
    case Bar = 'bar';
    case Entrance = 'entrance';
    case Window = 'window';
    case Wall = 'wall';
    case Label = 'label';
}
