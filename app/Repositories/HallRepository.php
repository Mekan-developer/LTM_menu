<?php

namespace App\Repositories;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\HallElement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

class HallRepository
{
    /** @return Collection<int, Hall> */
    public function all(): Collection
    {
        return Hall::query()
            ->withCount(['tables' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * Столы зала вместе с активным заказом и официантом — один запрос (LEFT JOIN).
     *
     * @return BaseCollection<int, \stdClass>
     */
    public function boardRows(Hall $hall): BaseCollection
    {
        return DB::table('dining_tables as t')
            ->leftJoin('orders as o', fn ($j) => $j->on('o.dining_table_id', '=', 't.id')
                ->whereIn('o.status', OrderStatus::activeValues()))
            ->leftJoin('users as u', 'u.id', '=', 'o.waiter_id')
            ->where('t.hall_id', $hall->id)
            ->where('t.is_active', true)
            ->whereNull('t.deleted_at')
            ->orderBy('t.sort')->orderBy('t.id')->orderByDesc('o.id')
            ->get([
                't.id', 't.number', 't.seats', 't.qr_token', 't.shape', 't.pos_x', 't.pos_y', 't.width', 't.height', 't.rotation',
                'o.id as order_id', 'o.status as order_status', 'o.total', 'o.opened_at', 'o.waiter_id',
                'u.name as waiter_name',
            ]);
    }

    /**
     * Сколько позиций «ждёт QR» и «готово» в каждом заказе — один агрегирующий запрос.
     *
     * @param  array<int, int>  $orderIds
     * @return BaseCollection<int, \stdClass>
     */
    public function itemCounts(array $orderIds): BaseCollection
    {
        if ($orderIds === []) {
            return collect();
        }

        return DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->whereIn('status', [OrderItemStatus::Pending->value, OrderItemStatus::Ready->value])
            ->groupBy('order_id', 'status')
            ->selectRaw('order_id, status, count(*) as n')
            ->get();
    }

    /** @return Collection<int, HallElement> */
    public function elements(Hall $hall): Collection
    {
        return $hall->elements()->orderBy('id')->get();
    }

    /** @return Collection<int, DiningTable> */
    public function activeTables(Hall $hall): Collection
    {
        return $hall->tables()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }

    /**
     * Столы зала с активным заказом.
     *
     * @return list<int>
     */
    public function busyTableIds(Hall $hall): array
    {
        return array_values(DB::table('orders')
            ->join('dining_tables', 'dining_tables.id', '=', 'orders.dining_table_id')
            ->where('dining_tables.hall_id', $hall->id)
            ->whereIn('orders.status', OrderStatus::activeValues())
            ->pluck('dining_tables.id')->map(fn ($id) => (int) $id)->unique()->values()->all());
    }

    public function nextSort(): int
    {
        return (int) Hall::max('sort') + 1;
    }
}
