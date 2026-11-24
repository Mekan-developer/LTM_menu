<?php

namespace App\Repositories;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /**
     * Топ блюд по количеству в оплаченных заказах за интервал.
     *
     * @return Collection<int, \stdClass>
     */
    public function topDishes(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Paid->value)
            ->whereBetween('orders.paid_at', [$from, $to])
            ->whereIn('order_items.status', OrderItemStatus::billable())
            ->groupBy('order_items.name_tm', 'order_items.name_ru')
            ->selectRaw('order_items.name_tm, order_items.name_ru, sum(order_items.quantity) as qty, sum(order_items.quantity * order_items.price) as amount')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();
    }

    public function activeOrdersCount(): int
    {
        // Занятые столы: заказы доставки и самовывоза стол не занимают.
        return DB::table('orders')->whereIn('status', OrderStatus::activeValues())->whereNotNull('dining_table_id')->count();
    }

    public function tablesCount(): int
    {
        return DB::table('dining_tables')->where('is_active', true)->count();
    }

    /**
     * Продажи блюд в оплаченных счетах за период (ТЗ 2.10, отчёт «Блюда»).
     *
     * @return Collection<int, \stdClass>
     */
    public function dishSales(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Paid->value)
            ->whereBetween('orders.paid_at', [$from, $to])
            ->whereIn('order_items.status', OrderItemStatus::billable())
            ->groupBy('order_items.dish_id', 'order_items.name_tm', 'order_items.name_ru')
            ->selectRaw('order_items.dish_id, order_items.name_tm, order_items.name_ru, sum(order_items.quantity) as qty, sum(order_items.quantity * order_items.price) as amount')
            ->get();
    }

    /**
     * Активные блюда меню — чтобы в «самых редких» были и те, что не продавались вовсе.
     *
     * @return Collection<int, \stdClass>
     */
    public function activeDishes(): Collection
    {
        return DB::table('dishes')->where('is_active', true)->get(['id', 'name_tm', 'name_ru']);
    }

    /**
     * Выручка и число счетов по официантам за период.
     *
     * @return Collection<int, \stdClass>
     */
    public function revenueByWaiter(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereNull('payments.cancelled_at')
            ->whereBetween('payments.created_at', [$from, $to])
            ->whereNotNull('orders.waiter_id')
            ->groupBy('orders.waiter_id')
            ->selectRaw('orders.waiter_id, count(*) as bills, sum(payments.total) as revenue')
            ->get();
    }

    /**
     * Удалённые блюда в заказах официанта (отклонённые QR-заказы не считаются).
     *
     * @return Collection<int, \stdClass>
     */
    public function removedByWaiter(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.status', OrderItemStatus::Removed->value)
            ->where(fn ($q) => $q->whereNull('order_items.removed_reason')
                ->orWhere('order_items.removed_reason', '!=', 'qr_rejected'))
            ->whereBetween('order_items.updated_at', [$from, $to])
            ->whereNotNull('orders.waiter_id')
            ->groupBy('orders.waiter_id')
            ->selectRaw('orders.waiter_id, sum(order_items.quantity) as removed')
            ->get();
    }

    /**
     * Интервалы занятости столов, пересекающие период (для отчёта «Загрузка зала»).
     *
     * @return Collection<int, \stdClass>
     */
    public function tableOccupancy(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('orders')
            ->whereNotNull('dining_table_id')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('opened_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $from))
            ->get(['dining_table_id', 'opened_at', 'closed_at']);
    }

    /**
     * Выручка по каналам за период: зал, доставка, самовывоз (ТЗ 2.10).
     *
     * @return Collection<string, int> channel => сумма в тенге
     */
    public function revenueByChannel(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereNull('payments.cancelled_at')
            ->whereBetween('payments.created_at', [$from, $to])
            ->groupBy('orders.channel')
            ->selectRaw('orders.channel, sum(payments.total) as revenue')
            ->pluck('revenue', 'channel')
            ->map(fn ($v) => (int) $v);
    }

    /**
     * Гости, у которых в периоде был оплаченный счёт (отчёт «Постоянные гости», ТЗ 2.10).
     * Визит — неотменённая оплата заказа с guest_id. first_at — самый первый визит за всё время,
     * по нему гость делится на нового (первый визит в периоде) и повторного (бывал раньше).
     *
     * @return Collection<int, \stdClass> guest_id, name, phone, visits, spent, first_at, last_at
     */
    public function guestVisits(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('guests', 'guests.id', '=', 'orders.guest_id')
            ->whereNull('payments.cancelled_at')
            ->where('payments.created_at', '<=', $to)
            ->groupBy('guests.id', 'guests.name', 'guests.phone')
            ->havingRaw('sum(case when payments.created_at >= ? then 1 else 0 end) > 0', [$from])
            ->selectRaw(
                'guests.id as guest_id, guests.name, guests.phone,
                 sum(case when payments.created_at >= ? then 1 else 0 end) as visits,
                 sum(case when payments.created_at >= ? then payments.total else 0 end) as spent,
                 min(payments.created_at) as first_at, max(payments.created_at) as last_at',
                [$from, $from],
            )
            ->get();
    }

    /**
     * Визиты гостей в периоде — для разбивки по дням.
     *
     * @return Collection<int, \stdClass> guest_id, created_at
     */
    public function guestPayments(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereNull('payments.cancelled_at')
            ->whereBetween('payments.created_at', [$from, $to])
            ->whereNotNull('orders.guest_id')
            ->get(['orders.guest_id', 'payments.created_at']);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, string>
     */
    public function userNames(array $ids): Collection
    {
        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id');
    }
}
