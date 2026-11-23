<?php

namespace App\Enums;

/**
 * Роли из ТЗ, которые нельзя удалить. Права у них меняются (кроме Владельца).
 * Курьер и бухгалтер — следующий этап.
 */
enum SystemRole: string
{
    case Owner = 'owner';
    case HallAdmin = 'hall_admin';
    case Waiter = 'waiter';
    case Cook = 'cook';
    case Cashier = 'cashier';

    public function nameTm(): string
    {
        return match ($this) {
            self::Owner => 'Eýesi',
            self::HallAdmin => 'Zal administratory',
            self::Waiter => 'Ofisiant',
            self::Cook => 'Aşpez / barmen',
            self::Cashier => 'Kassir',
        };
    }

    public function nameRu(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::HallAdmin => 'Администратор зала',
            self::Waiter => 'Официант',
            self::Cook => 'Повар / бармен',
            self::Cashier => 'Кассир',
        };
    }

    /** Права по умолчанию (раздел 1.3 ТЗ). У владельца права не хранятся — есть всегда. */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => [],
            self::HallAdmin => [
                Permission::RemoveAfterKitchen, Permission::TransferTables, Permission::StopList,
                Permission::Tables, Permission::AreaWaiter,
            ],
            self::Waiter => [Permission::AreaWaiter],
            self::Cook => [Permission::StopList, Permission::AreaKitchen],
            self::Cashier => [Permission::DiscountUpToK, Permission::AreaCashier],
        };
    }
}
