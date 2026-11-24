<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\Station;
use App\Events\KitchenBoardChanged;
use App\Events\WaiterAlert;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\OrderRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Экран кухни (ТЗ 2.5): карточки заказов по цехам, «Готово» видит официант. */
class KitchenService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly Realtime $realtime,
        private readonly DeliveryService $deliveries,
    ) {}

    /**
     * Карточка = одна отправка (batch) одного заказа в одном цехе.
     *
     * @return array<string, mixed>
     */
    public function board(Station $station): array
    {
        $tickets = $this->orders->kitchenQueue($station)
            ->groupBy(fn (OrderItem $i) => $i->order_id.'-'.$i->batch)
            ->map(function (Collection $items) {
                /** @var OrderItem $first */
                $first = $items->first();

                return [
                    'key' => $first->order_id.'-'.$first->batch,
                    'order_id' => $first->order_id,
                    'batch' => $first->batch,
                    'table' => $first->order->table?->number,
                    'waiter' => $first->order->waiter?->name,
                    'source' => $first->order->source->value,
                    // Доставка и самовывоз: вместо стола — номер заказа (ТЗ 2.5).
                    'channel' => $first->order->channel->value,
                    'sent_at' => $items->min('sent_at')?->toIso8601String(),
                    'items' => $items->map(fn (OrderItem $i) => [
                        'id' => $i->id,
                        'qty' => $i->quantity,
                        'name_tm' => $i->name_tm,
                        'name_ru' => $i->name_ru,
                        'comment' => $i->comment,
                    ])->values()->all(),
                ];
            })
            ->sortBy('sent_at')
            ->values()
            ->all();

        $pickup = $this->orders->waitingPickup($station)
            ->groupBy(fn (OrderItem $i) => $i->order_id.'-'.$i->batch)
            ->map(fn (Collection $items) => [
                'key' => $items->first()->order_id.'-'.$items->first()->batch,
                'table' => $items->first()->order->table?->number,
                'order_id' => $items->first()->order_id,
                'channel' => $items->first()->order->channel->value,
                'ready_at' => $items->max('ready_at')?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ['tickets' => $tickets, 'pickup' => $pickup];
    }

    public function markReady(Order $order, int $batch, Station $station): void
    {
        $items = $this->orders->itemsOfBatch($order, $batch, OrderItemStatus::Sent, $station);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['batch' => __('crm.order.batch_not_found')]);
        }

        $order->items()->whereIn('id', $items->pluck('id'))->update([
            'status' => OrderItemStatus::Ready->value,
            'ready_at' => now(),
        ]);

        $order->loadMissing('table');
        $this->realtime->send(new KitchenBoardChanged([$station->value]));

        // Доставка: официанта не зовём, заказ становится «готов», когда готовы все позиции.
        if (! $order->isHall()) {
            $this->deliveries->kitchenReady($order);

            return;
        }

        $this->realtime->send(new WaiterAlert('ready', $order->id, $order->table?->number, $order->waiter_id, $batch));
        $this->realtime->hallOf($order);
    }
}
