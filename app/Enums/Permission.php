<?php

namespace App\Enums;

/**
 * Права из раздела 1.3 ТЗ (подмножество MVP) + доступ к рабочим экранам.
 * Ключ права хранится строкой в role_permissions.permission.
 */
enum Permission: string
{
    case Settings = 'settings';
    case Menu = 'menu_prices';
    case Staff = 'staff_permissions';
    case Reports = 'reports';
    case ActionLog = 'action_log';
    case Reviews = 'reviews';
    case CancelPayment = 'cancel_payment';
    case DiscountUnlimited = 'discount_unlimited';
    case DiscountUpToK = 'discount_up_to_k';
    case RemoveAfterKitchen = 'remove_after_kitchen';
    case TransferTables = 'transfer_tables';
    case StopList = 'stop_list';
    case Tables = 'tables';
    case AreaWaiter = 'area_waiter';
    case AreaKitchen = 'area_kitchen';
    case AreaCashier = 'area_cashier';

    /** Группы для матрицы прав в админке. */
    public function group(): string
    {
        return match ($this) {
            self::AreaWaiter, self::AreaKitchen, self::AreaCashier => 'areas',
            self::Settings, self::Menu, self::Staff, self::Tables, self::StopList => 'management',
            self::Reports, self::ActionLog, self::Reviews => 'control',
            default => 'operations',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
