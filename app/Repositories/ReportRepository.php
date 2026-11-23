<?php

namespace App\Repositories;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    /** Топ блюд по количеству в оплаченных заказах за интервал. */
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
        return DB::table('orders')->whereIn('status', OrderStatus::activeValues())->count();
    }

    public function tablesCount(): int
    {
        return DB::table('dining_tables')->where('is_active', true)->count();
    }
}
