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
    case Guests = 'guests';
    case Bookings = 'bookings';
    case ExportGuests = 'export_guests';
    case Delivery = 'delivery';
    case Orders = 'orders'; // все заказы за период — мониторинг
    case Costing = 'costing'; // продукты, состав блюд, себестоимость и наценка (ТЗ 2.3)
    case Stock = 'stock'; // остатки: приход, списание, инвентаризация, «Нужно закупить» (ТЗ 2.3)
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
    case AreaCourier = 'area_courier';

    /** Группы для матрицы прав в админке. */
    public function group(): string
    {
        return match ($this) {
            self::AreaWaiter, self::AreaKitchen, self::AreaCashier, self::AreaCourier => 'areas',
            self::Settings, self::Menu, self::Staff, self::Tables, self::StopList, self::Guests, self::Bookings, self::Delivery, self::Costing, self::Stock => 'management',
            self::Reports, self::ActionLog, self::Reviews, self::ExportGuests, self::Orders => 'control',
            default => 'operations',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
