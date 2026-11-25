<?php

namespace App\Repositories;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\ActionLog;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * Экран «Все заказы»: зал, QR, доставка и самовывоз за период в одном списке.
 * Период — по времени открытия заказа.
 *
 * @phpstan-type Filters array{channel: ?string, status: ?string, waiter: ?int, q: ?string}
 */
class OrderMonitorRepository
{
    /**
     * @param  Filters  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(CarbonInterface $from, CarbonInterface $to, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->base($from, $to, $filters)
            ->when($filters['status'] === 'active', fn (Builder $q) => $q->whereIn('orders.status', OrderStatus::activeValues()))
            ->when($filters['status'] !== null && $filters['status'] !== 'active', fn (Builder $q) => $q->where('orders.status', $filters['status']))
            ->with([
                'table:id,number',
                'waiter:id,name',
                'guest:id,name,phone',
                'delivery:id,order_id,status,customer_name,phone',
                'activePayment',
            ])
            ->withSum(['items as items_qty' => fn ($q) => $q->whereIn('status', OrderItemStatus::billable())], 'quantity')
            ->orderByDesc('orders.opened_at')
            ->orderByDesc('orders.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Сводка за период по тем же фильтрам, но по всем статусам — чтобы плашки не обнулялись при выборе статуса.
     *
     * @param  Filters  $filters
     * @return array{count: int, active: int, paid: int, cancelled: int, revenue: int}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, array $filters): array
    {
        $rows = $this->base($from, $to, $filters)
            ->toBase()
            ->select('orders.status', DB::raw('count(*) as n'), DB::raw('coalesce(sum(orders.total), 0) as sum'))
            ->groupBy('orders.status')
            ->get()
            ->keyBy('status');

        $n = fn (string ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $rows->get($s)->n ?? 0);

        return [
            'count' => (int) $rows->sum('n'),
            'active' => $n(...OrderStatus::activeValues()),
            'paid' => $n(OrderStatus::Paid->value),
            'cancelled' => $n(OrderStatus::Cancelled->value, OrderStatus::Refunded->value),
            'revenue' => (int) ($rows->get(OrderStatus::Paid->value)->sum ?? 0),
        ];
    }

    /**
     * Официанты, у которых есть заказы за период, — для фильтра.
     *
     * @return BaseCollection<int, array{id: int, name: string}>
     */
    public function waiters(CarbonInterface $from, CarbonInterface $to): BaseCollection
    {
        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.waiter_id')
            ->whereBetween('orders.opened_at', [$from, $to])
            ->distinct()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name]);
    }

    public function detail(int $id): ?Order
    {
        return Order::query()
            ->with([
                'table:id,number,hall_id', 'table.hall:id,name_tm,name_ru',
                'waiter:id,name', 'guest:id,name,phone',
                'items' => fn ($q) => $q->orderBy('id'),
                'items.addedBy:id,name', 'items.removedBy:id,name',
                'delivery.courier:id,name',
                'payments' => fn ($q) => $q->orderBy('id'),
                'payments.cashier:id,name', 'payments.tips',
            ])
            ->find($id);
    }

    /**
     * Журнал действий по заказу: сам заказ, его позиции, оплаты и доставка.
     *
     * @return Collection<int, ActionLog>
     */
    public function logs(Order $order): Collection
    {
        $subjects = [
            'order' => [$order->id],
            'order_item' => $order->items->pluck('id')->all(),
            'payment' => $order->payments->pluck('id')->all(),
            'delivery' => $order->delivery ? [$order->delivery->id] : [],
        ];

        return ActionLog::query()
            ->with('user:id,name')
            ->where(function (Builder $q) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    if ($ids !== []) {
                        $q->orWhere(fn (Builder $w) => $w->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Filters  $filters
     * @return Builder<Order>
     */
    private function base(CarbonInterface $from, CarbonInterface $to, array $filters): Builder
    {
        return Order::query()
            ->select('orders.*')
            ->whereBetween('orders.opened_at', [$from, $to])
            ->when($filters['channel'], fn (Builder $q, string $channel) => $q->where('orders.channel', $channel))
            ->when($filters['waiter'], fn (Builder $q, int $waiter) => $q->where('orders.waiter_id', $waiter))
            ->when($filters['q'], fn (Builder $q, string $term) => $this->search($q, $term));
    }

    /**
     * Поиск: цифры — номер заказа, номер стола или часть телефона; текст — имя гостя.
     *
     * @param  Builder<Order>  $q
     */
    private function search(Builder $q, string $term): void
    {
        $term = mb_strtolower(ltrim($term, '#№ '));
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $q->where(function (Builder $w) use ($term, $digits) {
            if ($digits !== '' && $digits === preg_replace('/\s+/', '', $term)) {
                $w->where('orders.id', (int) $digits)
                    ->orWhereHas('table', fn (Builder $t) => $t->withTrashed()->where('number', $digits));

                if (strlen($digits) >= 3) {
                    $w->orWhereHas('delivery', fn (Builder $d) => $d->where('phone', 'like', '%'.$digits.'%'))
                        ->orWhereHas('guest', fn (Builder $g) => $g->where('phone', 'like', '%'.$digits.'%'));
                }

                return;
            }

            $like = '%'.$term.'%';
            $w->whereHas('delivery', fn (Builder $d) => $d->whereRaw('lower(customer_name) like ?', [$like]))
                ->orWhereHas('guest', fn (Builder $g) => $g->whereRaw('lower(name) like ?', [$like]))
                ->orWhereHas('waiter', fn (Builder $u) => $u->whereRaw('lower(name) like ?', [$like]));
        });
    }
}
