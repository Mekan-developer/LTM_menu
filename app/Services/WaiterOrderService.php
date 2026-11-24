<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Events\CashierQueueChanged;
use App\Events\HallChanged;
use App\Events\KitchenBoardChanged;
use App\Events\WaiterAlert;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Repositories\MenuRepository;
use App\Repositories\OrderRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Работа официанта со столом (ТЗ 2.4): открыть стол, добавить блюда, «На кухню», счёт. */
class WaiterOrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly MenuRepository $menu,
        private readonly StopListService $stopList,
        private readonly OrderCalculator $calculator,
        private readonly ActionLogger $logger,
        private readonly Realtime $realtime,
    ) {}

    public function openTable(DiningTable $table, User $waiter): Order
    {
        return DB::transaction(function () use ($table, $waiter) {
            $existing = $this->orders->activeForTableLocked($table);

            if ($existing !== null) {
                $this->assertCanHandle($existing, $waiter);

                return $existing;
            }

            $order = Order::create([
                'public_token' => Str::lower(Str::random(32)),
                'dining_table_id' => $table->id,
                'waiter_id' => $waiter->id,
                'source' => OrderSource::Waiter->value,
                'status' => OrderStatus::Open->value,
                'opened_at' => now(),
            ]);

            DB::afterCommit(fn () => $this->realtime->hallOf($order));

            return $order;
        });
    }

    /** @param list<array{dish_id:int, quantity:int, comment?:string|null}> $lines */
    public function addDraftItems(Order $order, array $lines, User $waiter): void
    {
        $this->assertCanHandle($order, $waiter);
        $this->assertActive($order);

        $dishes = $this->menu->activeDishesByIds(array_column($lines, 'dish_id'));

        foreach ($lines as $line) {
            $dish = $dishes->get($line['dish_id']);

            if ($dish === null || $this->stopList->isStopped($dish)) {
                throw ValidationException::withMessages(['items' => __('crm.order.dish_unavailable')]);
            }

            $order->items()->create([
                'dish_id' => $dish->id,
                'name_tm' => $dish->name_tm,
                'name_ru' => $dish->name_ru,
                'price' => $dish->price,
                'quantity' => $line['quantity'],
                'station' => $dish->station->value,
                'status' => OrderItemStatus::Draft->value,
                'comment' => $line['comment'] ?? null,
                'added_by' => $waiter->id,
            ]);
        }
    }

    public function updateDraft(OrderItem $item, int $quantity, ?string $comment, User $waiter): void
    {
        $this->assertCanHandle($item->loadMissing('order')->order, $waiter);

        if ($item->status !== OrderItemStatus::Draft) {
            throw ValidationException::withMessages(['item' => __('crm.order.only_draft')]);
        }

        $item->update(['quantity' => $quantity, 'comment' => $comment]);
    }

    /**
     * Неотправленную позицию можно убрать свободно. Отправленную на кухню —
     * только с правом «Удаление блюд после кухни» и с причиной (ТЗ 2.4, 2.11).
     */
    public function removeItem(OrderItem $item, User $user, ?string $reason): void
    {
        $order = $item->loadMissing('order')->order;
        $this->assertCanHandle($order, $user);

        if ($item->status === OrderItemStatus::Removed) {
            return;
        }

        if ($item->status === OrderItemStatus::Draft) {
            $item->delete();

            return;
        }

        if (! $user->hasPermission(Permission::RemoveAfterKitchen)) {
            throw new AuthorizationException(__('crm.order.remove_forbidden'));
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('crm.order.reason_required')]);
        }

        $wasOnKitchen = $item->status === OrderItemStatus::Sent;

        DB::transaction(function () use ($item, $order, $user, $reason) {
            $item->update([
                'status' => OrderItemStatus::Removed->value,
                'removed_reason' => $reason,
                'removed_by' => $user->id,
            ]);
            $this->calculator->recalculate($order);
            $this->logger->log(ActionLogger::ITEM_REMOVED, $item, [
                'order_id' => $order->id, 'name' => $item->name_ru, 'qty' => $item->quantity, 'reason' => $reason,
            ], $user);
        });

        if ($wasOnKitchen) {
            $this->realtime->send(new KitchenBoardChanged([$item->station->value]));
        }
    }

    public function sendToKitchen(Order $order, User $waiter): int
    {
        $this->assertCanHandle($order, $waiter);
        $this->assertActive($order);

        $stations = DB::transaction(function () use ($order, $waiter) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $drafts = $order->items()->where('status', OrderItemStatus::Draft->value)->get();

            if ($drafts->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('crm.order.nothing_to_send')]);
            }

            $batch = $order->batch_counter + 1;
            $order->forceFill([
                'batch_counter' => $batch,
                'waiter_id' => $order->waiter_id ?? $waiter->id,
            ])->save();

            $order->items()->whereIn('id', $drafts->pluck('id'))->update([
                'status' => OrderItemStatus::Sent->value,
                'batch' => $batch,
                'sent_at' => now(),
            ]);

            $this->calculator->recalculate($order);

            return array_values($drafts->map(fn (OrderItem $i) => $i->station->value)->unique()->all());
        });

        $this->realtime->send(new KitchenBoardChanged($stations));

        return count($stations);
    }

    /** Подтверждение QR-заказа официантом защищает от ложных заказов по фото QR (ТЗ 2.4). */
    public function confirmQrBatch(Order $order, int $batch, User $waiter): void
    {
        $this->assertCanHandle($order, $waiter);

        $stations = DB::transaction(function () use ($order, $batch, $waiter) {
            $items = $this->orders->itemsOfBatch($order, $batch, OrderItemStatus::Pending);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['batch' => __('crm.order.batch_not_found')]);
            }

            $order->items()->whereIn('id', $items->pluck('id'))->update([
                'status' => OrderItemStatus::Sent->value,
                'sent_at' => now(),
            ]);

            if ($order->waiter_id === null) {
                $order->forceFill(['waiter_id' => $waiter->id])->save();
            }

            $this->calculator->recalculate($order);

            return array_values($items->map(fn (OrderItem $i) => $i->station->value)->unique()->all());
        });

        $this->realtime->send(new KitchenBoardChanged($stations));
        $this->realtime->hallOf($order);
    }

    public function rejectQrBatch(Order $order, int $batch, User $waiter): void
    {
        $this->assertCanHandle($order, $waiter);

        DB::transaction(function () use ($order, $batch, $waiter) {
            $items = $this->orders->itemsOfBatch($order, $batch, OrderItemStatus::Pending);

            $order->items()->whereIn('id', $items->pluck('id'))->update([
                'status' => OrderItemStatus::Removed->value,
                'removed_reason' => 'qr_rejected',
                'removed_by' => $waiter->id,
            ]);

            $this->logger->log(ActionLogger::QR_REJECTED, $order, ['batch' => $batch, 'items' => $items->count()], $waiter);

            $left = $order->items()->where('status', '!=', OrderItemStatus::Removed->value)->exists();

            if (! $left) {
                $order->forceFill(['status' => OrderStatus::Cancelled->value, 'closed_at' => now()])->save();
            }
        });

        $this->realtime->hallOf($order);
    }

    public function markServed(Order $order, int $batch, User $waiter): void
    {
        $this->assertCanHandle($order, $waiter);

        $order->items()
            ->where('batch', $batch)
            ->where('status', OrderItemStatus::Ready->value)
            ->update(['status' => OrderItemStatus::Served->value, 'served_at' => now()]);

        $this->realtime->hallOf($order);
    }

    public function requestBill(Order $order, User $waiter): void
    {
        $this->assertCanHandle($order, $waiter);
        $this->assertActive($order);

        $order->forceFill([
            'status' => OrderStatus::BillRequested->value,
            'bill_requested_at' => now(),
        ])->save();

        $this->realtime->send(new CashierQueueChanged($order->id));
        $this->realtime->hallOf($order);
    }

    /** Передать стол другому официанту — право «Передача столов» (ТЗ 2.4). */
    public function transfer(Order $order, User $target, User $actor): void
    {
        if (! $actor->hasPermission(Permission::TransferTables)) {
            throw new AuthorizationException(__('crm.errors.forbidden'));
        }

        if (! $target->is_active || ! $target->hasPermission(Permission::AreaWaiter)) {
            throw ValidationException::withMessages(['waiter_id' => __('crm.order.bad_waiter')]);
        }

        $from = $order->waiter_id;
        $order->forceFill(['waiter_id' => $target->id])->save();

        $this->logger->log(ActionLogger::TABLE_TRANSFERRED, $order, ['from' => $from, 'to' => $target->id], $actor);
        $this->realtime->send(new WaiterAlert('transfer', $order->id, $order->table?->number, $target->id));
        $this->realtime->hallOf($order);
    }

    /**
     * Перенос заказа за другой стол (ТЗ 2.4): гости пересели. Стол должен быть свободен.
     * Строка целевого стола блокируется (lockForUpdate), чтобы двое не пересадили гостей
     * на один стол одновременно. Права — как у остальных действий с заказом.
     */
    public function moveToTable(Order $order, DiningTable $target, User $user): void
    {
        $this->assertCanHandle($order, $user);
        $this->assertActive($order);

        if ($target->id === $order->dining_table_id) {
            throw ValidationException::withMessages(['table_id' => __('crm.order.table_unavailable')]);
        }

        $fromHall = $order->table?->hall_id;

        DB::transaction(function () use ($order, $target, $user) {
            $locked = DiningTable::query()->lockForUpdate()->find($target->id);

            if ($locked === null || ! $locked->is_active) {
                throw ValidationException::withMessages(['table_id' => __('crm.order.table_unavailable')]);
            }

            if ($this->orders->activeForTableLocked($locked) !== null) {
                throw ValidationException::withMessages(['table_id' => __('crm.order.table_busy')]);
            }

            $from = $order->table?->number;
            $order->forceFill(['dining_table_id' => $locked->id])->save();

            $this->logger->log(ActionLogger::TABLE_MOVED, $order, ['from' => $from, 'to' => $locked->number], $user);
        });

        // Номер стола виден на кухне, в кассе и на схеме.
        $this->realtime->send(new KitchenBoardChanged(['kitchen', 'bar']));
        $this->realtime->send(new CashierQueueChanged($order->id));
        if ($fromHall !== null && $fromHall !== $target->hall_id) {
            $this->realtime->send(new HallChanged($fromHall));
        }
        $this->realtime->hallOf($order->unsetRelation('table'));
    }

    public function authorizeHandle(Order $order, User $user): void
    {
        $this->assertCanHandle($order, $user);
    }

    private function assertCanHandle(Order $order, User $user): void
    {
        // Доставка и самовывоз ведутся на экране «Доставка», не у официанта.
        if (! $order->isHall()) {
            throw new AuthorizationException(__('crm.order.not_your_table'));
        }

        $own = $order->waiter_id === null || (int) $order->waiter_id === (int) $user->id;

        if (! $own && ! $user->hasPermission(Permission::TransferTables)) {
            throw new AuthorizationException(__('crm.order.not_your_table'));
        }
    }

    private function assertActive(Order $order): void
    {
        if (! $order->isActive()) {
            throw ValidationException::withMessages(['order' => __('crm.order.closed')]);
        }
    }
}
