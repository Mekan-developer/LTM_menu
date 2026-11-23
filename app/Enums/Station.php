<?php

namespace App\Enums;

/** Цех: блюда кухни и бара идут на разные экраны. */
enum Station: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
}
