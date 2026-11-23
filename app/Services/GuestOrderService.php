<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Events\KitchenBoardChanged;
use App\Events\WaiterAlert;
use App\Exceptions\CartChangedException;
use App\Models\DiningTable;
use App\Models\Order;
use App\Repositories\MenuRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Заказ гостя по QR-коду стола (ТЗ 2.3, 2.4): без регистрации и SMS.
 * Сервер сверяет цены и стоп-лист в момент заказа.
 */
class GuestOrderService
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly OrderRepository $orders,
        private readonly StopListService $stopList,
        private readonly OrderCalculator $calculator,
        private readonly SettingsService $settings,
        private readonly Realtime $realtime,
    ) {}

    /** @param list<array{dish_id:int, quantity:int, price:int}> $lines */
    public function place(DiningTable $table, array $lines, ?string $comment): Order
    {
        $dishes = $this->menu->activeDishesByIds(array_column($lines, 'dish_id'));
        $problems = [];

        foreach ($lines as $line) {
            $dish = $dishes->get($line['dish_id']);

            if ($dish === null || $this->stopList->isStopped($dish)) {
                $problems[] = ['dish_id' => (int) $line['dish_id'], 'type' => 'unavailable'];
            } elseif ($dish->price !== (int) $line['price']) {
                $problems[] = ['dish_id' => $dish->id, 'type' => 'price_changed', 'old' => (int) $line['price'], 'new' => $dish->price];
            }
        }

        if ($problems !== []) {
            throw new CartChangedException($problems);
        }

        $autoSend = ! (bool) $this->settings->get('qr_confirmation');

        [$order, $batch, $stations] = DB::transaction(function () use ($table, $lines, $dishes, $comment, $autoSend) {
            $order = $this->orders->activeForTableLocked($table) ?? Order::create([
                'public_token' => Str::lower(Str::random(32)),
                'dining_table_id' => $table->id,
                'waiter_id' => null,
                'source' => OrderSource::Qr->value,
                'status' => OrderStatus::Open->value,
                'opened_at' => now(),
            ]);

            $batch = $order->batch_counter + 1;
            $order->forceFill([
                'batch_counter' => $batch,
                'guest_comment' => trim(implode("\n", array_filter([$order->guest_comment, $comment]))) ?: null,
            ])->save();

            $stations = [];

            foreach ($lines as $line) {
                $dish = $dishes->get($line['dish_id']);
                $stations[] = $dish->station->value;

                $order->items()->create([
                    'dish_id' => $dish->id,
                    'name_tm' => $dish->name_tm,
                    'name_ru' => $dish->name_ru,
                    'price' => $dish->price,
                    'quantity' => $line['quantity'],
                    'station' => $dish->station->value,
                    'status' => $autoSend ? OrderItemStatus::Sent->value : OrderItemStatus::Pending->value,
                    'comment' => $comment ? Str::limit($comment, 250) : null,
                    'batch' => $batch,
                    'sent_at' => $autoSend ? now() : null,
                ]);
            }

            $this->calculator->recalculate($order);

            return [$order, $batch, array_values(array_unique($stations))];
        });

        $this->realtime->send(new WaiterAlert('qr_order', $order->id, $table->number, $order->waiter_id, $batch));

        if ($autoSend) {
            $this->realtime->send(new KitchenBoardChanged($stations));
        }

        return $order;
    }
}
