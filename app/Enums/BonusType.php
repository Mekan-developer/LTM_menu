<?php

namespace App\Enums;

/** Операция с бонусами гостя (ТЗ 2.8). Отмена оплаты — обратные операции к начислению и списанию. */
enum BonusType: string
{
    case Accrual = 'accrual';
    case Spend = 'spend';
    case AccrualReversal = 'accrual_reversal';
    case SpendReversal = 'spend_reversal';
}
