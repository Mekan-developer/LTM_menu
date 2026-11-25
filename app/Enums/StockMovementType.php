<?php

namespace App\Enums;

/** Движение по складу (ТЗ 2.3): приход, продажа и её отмена, ручное списание, инвентаризация. */
enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case WriteOff = 'write_off';
    case Inventory = 'inventory';
}
