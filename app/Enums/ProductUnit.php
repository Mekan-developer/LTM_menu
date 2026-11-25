<?php

namespace App\Enums;

/** Единица продукта. В рецепте кг и л вводят граммами и миллилитрами, хранят — в кг и л. */
enum ProductUnit: string
{
    case Kg = 'kg';
    case L = 'l';
    case Pcs = 'pcs';
}
