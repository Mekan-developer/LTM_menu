<?php

namespace App\Enums;

/**
 * Роли из ТЗ (1.3), которые нельзя удалить. Права у них меняются (кроме Владельца).
 * Экран курьера появится вместе с доставкой (2.6), зарплата бухгалтера — с разделом 2.12.
 */
enum SystemRole: string
{
    case Owner = 'owner';
    case HallAdmin = 'hall_admin';
    case Waiter = 'waiter';
    case Cook = 'cook';
    case Cashier = 'cashier';
    case Courier = 'courier';
    case Accountant = 'accountant';

    public function nameTm(): string
    {
        return match ($this) {
            self::Owner => 'Eýesi',
            self::HallAdmin => 'Zal administratory',
            self::Waiter => 'Ofisiant',
            self::Cook => 'Aşpez / barmen',
            self::Cashier => 'Kassir',
            self::Courier => 'Kurýer',
            self::Accountant => 'Buhgalter',
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
            self::Courier => 'Курьер',
            self::Accountant => 'Бухгалтер',
        };
    }

    /**
     * Права по умолчанию (раздел 1.3 ТЗ). У владельца права не хранятся — есть всегда.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => [],
            self::HallAdmin => [
                Permission::RemoveAfterKitchen, Permission::TransferTables, Permission::StopList,
                Permission::Tables, Permission::AreaWaiter, Permission::Reviews, Permission::Guests, Permission::Bookings,
                Permission::Delivery, Permission::Orders, Permission::Shifts,
            ],
            self::Waiter => [Permission::AreaWaiter],
            self::Cook => [Permission::StopList, Permission::AreaKitchen],
            self::Cashier => [Permission::DiscountUpToK, Permission::AreaCashier],
            self::Courier => [Permission::AreaCourier],
            self::Accountant => [Permission::Reports, Permission::Orders],
        };
    }
}
