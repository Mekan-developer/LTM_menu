<?php

namespace App\Repositories;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\Station;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    public function activeForTableLocked(DiningTable $table): ?Order
    {
        return Order::query()
            ->where('dining_table_id', $table->id)
            ->whereIn('status', OrderStatus::activeValues())
            ->lockForUpdate()
            ->first();
    }

    /** @return list<int> */
    public function activeTableIds(): array
    {
        return Order::query()->whereIn('status', OrderStatus::activeValues())
            ->whereNotNull('dining_table_id')
            ->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all();
    }

    public function findByPublicToken(string $token): ?Order
    {
        return Order::query()->where('public_token', $token)->first();
    }

    /** Активные заказы для экрана официанта: свои + неназначенные QR. С правом передачи — все. */
    public function activeForWaiter(User $waiter, bool $seeAll): Collection
    {
        return Order::query()
            ->whereIn('status', OrderStatus::activeValues())
            ->when(! $seeAll, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('waiter_id', $waiter->id)
                ->orWhereNull('waiter_id')))
            ->with(['table', 'waiter:id,name', 'items' => fn ($q) => $q->where('status', '!=', OrderItemStatus::Removed->value)])
            ->orderBy('opened_at')
            ->get();
    }

    /** Активные заказы для кассы: «просят счёт» первыми. */
    public function activeForCashier(): Collection
    {
        return Order::query()
            ->whereIn('status', OrderStatus::activeValues())
            ->with(['table', 'waiter:id,name'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [OrderStatus::BillRequested->value])
            ->orderBy('bill_requested_at')
            ->orderBy('opened_at')
            ->get();
    }

    public function loadForScreen(Order $order): Order
    {
        return $order->load([
            'table', 'waiter:id,name', 'activePayment',
            'items' => fn ($q) => $q->orderBy('batch')->orderBy('id'),
        ]);
    }

    /** Позиции на кухне для цеха: отправлены, ещё не готовы. */
    public function kitchenQueue(Station $station): Collection
    {
        return OrderItem::query()
            ->where('station', $station->value)
            ->where('status', OrderItemStatus::Sent->value)
            ->with(['order:id,dining_table_id,waiter_id,source', 'order.table:id,number', 'order.waiter:id,name'])
            ->orderBy('sent_at')
            ->get();
    }

    /** Готово, но ещё не подано — нижняя строка экрана кухни. */
    public function waitingPickup(Station $station): Collection
    {
        return OrderItem::query()
            ->where('station', $station->value)
            ->where('status', OrderItemStatus::Ready->value)
            ->where('ready_at', '>=', now()->subHour())
            ->with(['order:id,dining_table_id', 'order.table:id,number'])
            ->orderBy('ready_at')
            ->get();
    }

    public function itemsOfBatch(Order $order, int $batch, OrderItemStatus $status, ?Station $station = null): Collection
    {
        return $order->items()
            ->where('batch', $batch)
            ->where('status', $status->value)
            ->when($station, fn ($q) => $q->where('station', $station->value))
            ->get();
    }
}
