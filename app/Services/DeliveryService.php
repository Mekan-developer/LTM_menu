<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Events\CashierQueueChanged;
use App\Events\DeliveriesChanged;
use App\Events\KitchenBoardChanged;
use App\Exceptions\CartChangedException;
use App\Models\Delivery;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Repositories\DeliveryRepository;
use App\Repositories\MenuRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Доставка и самовывоз (ТЗ 2.6). Заказ — обычный заказ без стола: кухня, пересчёт и оплата общие с залом.
 * Статусы: новый → принят → готовится → готов → у курьера → доставлен / выдан; или отменён с причиной.
 */
class DeliveryService
{
    public function __construct(
        private readonly DeliveryRepository $deliveries,
        private readonly MenuRepository $menu,
        private readonly StopListService $stopList,
        private readonly OrderCalculator $calculator,
        private readonly GuestService $guests,
        private readonly SettingsService $settings,
        private readonly CashierService $cashier,
        private readonly ActionLogger $logger,
        private readonly Realtime $realtime,
    ) {}

    /**
     * Новый заказ: со страницы для гостей (статус «новый», цены сверяются с меню гостя)
     * или по звонку (сразу «принят»). Гость попадает в базу гостей по телефону (ТЗ 2.1).
     *
     * @param  array<string, mixed>  $data  type, name, phone, address, method, comment, items[], delivery_fee — из DeliveryRequest
     */
    public function create(array $data, OrderSource $source, ?User $user = null): Order
    {
        /** @var list<array{dish_id:int, quantity:int, price?:int, comment?:string|null}> $items */
        $items = $data['items'];
        $name = trim((string) $data['name']);
        $channel = OrderChannel::from((string) $data['type']);
        $dishes = $this->dishesFor($items, checkPrices: $source === OrderSource::Online);
        $guest = $this->guests->findOrCreateByPhone((string) $data['phone'], $name, 'order', $user);
        $address = $channel === OrderChannel::Delivery ? trim((string) ($data['address'] ?? '')) : null;

        // Адрес доставки запоминается в карточке гостя, если там его ещё нет.
        if ($address && blank($guest->address)) {
            $guest->update(['address' => $address]);
        }

        $fee = $channel === OrderChannel::Delivery
            ? (int) ($data['delivery_fee'] ?? $this->settings->get('delivery_fee'))
            : 0;
        $byPhone = $source !== OrderSource::Online;

        $order = DB::transaction(function () use ($data, $items, $name, $channel, $source, $dishes, $guest, $address, $fee, $byPhone, $user) {
            $order = Order::create([
                'public_token' => Str::lower(Str::random(32)),
                'guest_id' => $guest->id,
                'source' => $source->value,
                'channel' => $channel->value,
                'status' => OrderStatus::Open->value,
                'guest_comment' => filled($data['comment'] ?? null) ? trim((string) $data['comment']) : null,
                'delivery_fee' => $fee,
                'opened_at' => now(),
            ]);

            foreach ($items as $line) {
                /** @var Dish $dish */
                $dish = $dishes->get($line['dish_id']);
                $order->items()->create([
                    'dish_id' => $dish->id,
                    'name_tm' => $dish->name_tm,
                    'name_ru' => $dish->name_ru,
                    'price' => $dish->price,
                    'quantity' => $line['quantity'],
                    'station' => $dish->station->value,
                    'status' => OrderItemStatus::Draft->value,
                    'comment' => filled($line['comment'] ?? null) ? Str::limit((string) $line['comment'], 250) : null,
                    'added_by' => $user?->id,
                ]);
            }

            $order->delivery()->create([
                'status' => ($byPhone ? DeliveryStatus::Accepted : DeliveryStatus::New)->value,
                'customer_name' => $name,
                'phone' => $guest->phone,
                'address' => $address,
                'planned_method' => PaymentMethod::from((string) $data['method'])->value,
                'created_by' => $user?->id,
                'accepted_at' => $byPhone ? now() : null,
            ]);

            return $this->calculator->recalculate($order);
        });

        $this->notify($order, DeliveryStatus::New);

        return $order;
    }

    /** Администратор принял заказ гостя со страницы. */
    public function accept(Delivery $delivery, User $user): void
    {
        $this->transition($delivery, [DeliveryStatus::New], DeliveryStatus::Accepted, ['accepted_at' => now()]);
    }

    /** «На кухню»: позиции уходят на экран кухни, статус «готовится» (ТЗ 2.6). Можно и из «нового». */
    public function sendToKitchen(Delivery $delivery, User $user): void
    {
        $stations = DB::transaction(function () use ($delivery) {
            $delivery = $this->lock($delivery);
            $this->assertStatus($delivery, [DeliveryStatus::New, DeliveryStatus::Accepted]);

            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);
            $drafts = $order->items()->where('status', OrderItemStatus::Draft->value)->get();

            if ($drafts->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('crm.order.nothing_to_send')]);
            }

            $batch = $order->batch_counter + 1;
            $order->forceFill(['batch_counter' => $batch])->save();
            $order->items()->whereIn('id', $drafts->pluck('id'))->update([
                'status' => OrderItemStatus::Sent->value,
                'batch' => $batch,
                'sent_at' => now(),
            ]);

            $delivery->forceFill([
                'status' => DeliveryStatus::Cooking->value,
                'accepted_at' => $delivery->accepted_at ?? now(),
                'cooking_at' => now(),
            ])->save();

            return array_values($drafts->map(fn (OrderItem $i) => $i->station->value)->unique()->all());
        });

        $this->realtime->send(new KitchenBoardChanged($stations));
        $this->notify($delivery->order, DeliveryStatus::Cooking, $delivery->courier_id);
    }

    /** Кухня нажала «Готово». Когда готовы все позиции — заказ «готов» (ТЗ 2.6). */
    public function kitchenReady(Order $order): void
    {
        $ready = DB::transaction(function () use ($order) {
            $delivery = $this->deliveries->lockForOrder($order->id);

            if ($delivery === null || $delivery->status !== DeliveryStatus::Cooking) {
                return false;
            }

            if ($order->items()->where('status', OrderItemStatus::Sent->value)->exists()) {
                return false;
            }

            $delivery->forceFill(['status' => DeliveryStatus::Ready->value, 'ready_at' => now()])->save();

            return true;
        });

        if ($ready) {
            $this->notify($order, DeliveryStatus::Ready, $order->delivery?->courier_id);

            // Готовый самовывоз появляется в очереди кассы: гость платит при выдаче.
            if ($order->channel === OrderChannel::Pickup) {
                $this->realtime->send(new CashierQueueChanged($order->id));
            }
        }
    }

    /** Администратор назначает курьера (или снимает назначение) — только доставке. */
    public function assignCourier(Delivery $delivery, ?User $courier, User $user): void
    {
        if ($delivery->order->channel !== OrderChannel::Delivery) {
            throw ValidationException::withMessages(['courier_id' => __('crm.delivery.pickup_no_courier')]);
        }

        if ($courier !== null && (! $courier->is_active || $courier->isOwner() || ! $courier->hasPermission(Permission::AreaCourier))) {
            throw ValidationException::withMessages(['courier_id' => __('crm.delivery.bad_courier')]);
        }

        $previous = DB::transaction(function () use ($delivery, $courier) {
            $delivery = $this->lock($delivery);
            $this->assertStatus($delivery, [DeliveryStatus::New, DeliveryStatus::Accepted, DeliveryStatus::Cooking, DeliveryStatus::Ready]);
            $previous = $delivery->courier_id;
            $delivery->forceFill(['courier_id' => $courier?->id])->save();

            return $previous;
        });

        $this->notify($delivery->order, $delivery->status, $courier->id ?? $previous);
    }

    /** Стоимость доставки меняется в заказе; пишется в журнал (ТЗ 2.6, 2.11). */
    public function setFee(Delivery $delivery, int $fee, User $user): void
    {
        if ($delivery->order->channel !== OrderChannel::Delivery) {
            throw ValidationException::withMessages(['delivery_fee' => __('crm.delivery.pickup_no_fee')]);
        }

        DB::transaction(function () use ($delivery, $fee, $user) {
            $delivery = $this->lock($delivery);
            $this->assertStatus($delivery, DeliveryStatus::active());

            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);
            $old = $order->delivery_fee;

            if ($old === $fee) {
                return;
            }

            $order->forceFill(['delivery_fee' => max(0, $fee)]);
            $this->calculator->recalculate($order);
            $this->logger->log(ActionLogger::DELIVERY_FEE_CHANGED, $order, ['old' => $old, 'new' => $fee], $user);
        });

        $this->notify($delivery->order, $delivery->status, $delivery->courier_id);
    }

    /** Готовый заказ передан курьеру. Отмечает сам курьер или администратор. */
    public function pickUp(Delivery $delivery, User $user): void
    {
        $this->assertCourierOrManager($delivery, $user);

        if ($delivery->courier_id === null) {
            throw ValidationException::withMessages(['courier_id' => __('crm.delivery.no_courier')]);
        }

        $this->transition($delivery, [DeliveryStatus::Ready], DeliveryStatus::OnWay, ['picked_at' => now()]);
        $this->handedOver($delivery->order);
    }

    /**
     * Заказ ушёл с кухни (курьер забрал или гость получил самовывоз): позиции «поданы»,
     * карточка пропадает из строки «Ждут выдачи» на экране кухни.
     */
    public function handedOver(Order $order): void
    {
        $stations = array_values($order->items()->where('status', OrderItemStatus::Ready->value)->get()
            ->map(fn (OrderItem $i) => $i->station->value)->unique()->all());

        if ($stations === []) {
            return;
        }

        $order->items()->where('status', OrderItemStatus::Ready->value)
            ->update(['status' => OrderItemStatus::Served->value, 'served_at' => now()]);
        $this->realtime->send(new KitchenBoardChanged($stations));
    }

    /**
     * Курьер вручил заказ и указал, как гость заплатил на самом деле (ТЗ 2.6):
     * наличные, карта (терминал курьера) или часть наличными и часть картой.
     * Если способ отличается от выбранного при заказе — запись в журнал. Чаевые — курьеру.
     *
     * @param  array{method:string, cash_amount?:string|null, tip_amount?:string|null, tip_method?:string|null}  $data
     */
    public function complete(Delivery $delivery, array $data, User $user): void
    {
        $this->assertCourierOrManager($delivery, $user);

        DB::transaction(function () use ($delivery, $data, $user) {
            $delivery = $this->lock($delivery);
            $this->assertStatus($delivery, [DeliveryStatus::OnWay]);

            $payment = $this->cashier->settle($delivery->order, [
                'method' => $data['method'],
                'cash_amount' => $data['cash_amount'] ?? null,
                'tip_amount' => $data['tip_amount'] ?? null,
                'tip_method' => $data['tip_method'] ?? null,
            ], $user, $delivery->courier_id);

            $delivery->forceFill(['status' => DeliveryStatus::Delivered->value, 'finished_at' => now()])->save();

            if ($payment->method !== $delivery->planned_method) {
                $this->logger->log(ActionLogger::PAYMENT_METHOD_CHANGED, $delivery->order, [
                    'planned' => $delivery->planned_method->value,
                    'actual' => $payment->method->value,
                    'cash' => $payment->cash_amount,
                    'card' => $payment->card_amount,
                ], $user);
            }
        });

        $this->notify($delivery->order, DeliveryStatus::Delivered, $delivery->courier_id);
        // В итоге дня кассы появились наличные «у курьера».
        $this->realtime->send(new CashierQueueChanged($delivery->order_id));
    }

    /** Отмена с причиной до вручения. Позиции снимаются с кухни, заказ отменён (ТЗ 2.6, 2.11). */
    public function cancel(Delivery $delivery, string $reason, User $user): void
    {
        $stations = DB::transaction(function () use ($delivery, $reason, $user) {
            $delivery = $this->lock($delivery);
            $this->assertStatus($delivery, DeliveryStatus::active());

            $order = Order::query()->lockForUpdate()->findOrFail($delivery->order_id);
            $onKitchen = array_values($order->items()->where('status', OrderItemStatus::Sent->value)->get()
                ->map(fn (OrderItem $i) => $i->station->value)->unique()->all());

            $order->items()->where('status', '!=', OrderItemStatus::Removed->value)->update([
                'status' => OrderItemStatus::Removed->value,
                'removed_reason' => Str::limit($reason, 250),
                'removed_by' => $user->id,
            ]);
            $order->forceFill(['status' => OrderStatus::Cancelled->value, 'closed_at' => now()])->save();
            $delivery->forceFill([
                'status' => DeliveryStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save();

            $this->logger->log(ActionLogger::ORDER_CANCELLED, $order, [
                'channel' => $order->channel->value, 'total' => $order->total, 'reason' => $reason,
            ], $user);

            return $onKitchen;
        });

        if ($stations !== []) {
            $this->realtime->send(new KitchenBoardChanged($stations));
        }

        $this->notify($delivery->order, DeliveryStatus::Cancelled, $delivery->courier_id);
    }

    /**
     * Кассир принял у курьера наличные по всем его доставленным заказам (ТЗ 2.6).
     *
     * @return int сумма в тенге
     */
    public function receiveCash(User $courier, User $cashier): int
    {
        $amount = DB::transaction(function () use ($courier, $cashier) {
            $rows = $this->deliveries->unreceivedCashLocked($courier->id);
            $amount = (int) $rows->sum(fn (Delivery $d) => $d->order->activePayment->cash_amount ?? 0);

            if ($rows->isEmpty()) {
                return 0;
            }

            Delivery::query()->whereIn('id', $rows->pluck('id'))->update([
                'cash_received_at' => now(),
                'cash_received_by' => $cashier->id,
            ]);

            $this->logger->log(ActionLogger::COURIER_CASH_RECEIVED, $courier, [
                'amount' => $amount, 'orders' => $rows->count(),
            ], $cashier);

            return $amount;
        });

        if ($amount > 0) {
            $this->realtime->send(new DeliveriesChanged(0, 'cash', $courier->id));
        }

        return $amount;
    }

    /**
     * Блюда заказа: только активные и не в стопе. Для гостя ещё и цена должна совпасть
     * с его сохранённым меню — иначе гость видит сообщение до отправки (ТЗ 2.3).
     *
     * @param  list<array{dish_id:int, quantity:int, price?:int}>  $lines
     * @return Collection<int, Dish>
     */
    private function dishesFor(array $lines, bool $checkPrices): Collection
    {
        $dishes = $this->menu->activeDishesByIds(array_column($lines, 'dish_id'));
        $problems = [];

        foreach ($lines as $line) {
            $dish = $dishes->get($line['dish_id']);

            if ($dish === null || $this->stopList->isStopped($dish)) {
                $problems[] = ['dish_id' => (int) $line['dish_id'], 'type' => 'unavailable'];
            } elseif ($checkPrices && $dish->price !== (int) ($line['price'] ?? $dish->price)) {
                $problems[] = ['dish_id' => $dish->id, 'type' => 'price_changed', 'old' => (int) ($line['price'] ?? 0), 'new' => $dish->price];
            }
        }

        if ($problems === []) {
            return $dishes;
        }

        if ($checkPrices) {
            throw new CartChangedException($problems);
        }

        throw ValidationException::withMessages(['items' => __('crm.order.dish_unavailable')]);
    }

    /**
     * @param  list<DeliveryStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function transition(Delivery $delivery, array $from, DeliveryStatus $to, array $extra = []): void
    {
        DB::transaction(function () use ($delivery, $from, $to, $extra) {
            $locked = $this->lock($delivery);
            $this->assertStatus($locked, $from);
            $locked->forceFill(['status' => $to->value, ...$extra])->save();
            $delivery->setRawAttributes($locked->getAttributes(), true);
        });

        $this->notify($delivery->order, $to, $delivery->courier_id);
    }

    private function lock(Delivery $delivery): Delivery
    {
        return Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
    }

    /** @param list<DeliveryStatus> $allowed */
    private function assertStatus(Delivery $delivery, array $allowed): void
    {
        if (! in_array($delivery->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('crm.delivery.bad_status')]);
        }
    }

    /** Курьер работает только со своими заказами; администратор доставки — со всеми. */
    private function assertCourierOrManager(Delivery $delivery, User $user): void
    {
        if ($user->hasPermission(Permission::Delivery)) {
            return;
        }

        if ($delivery->courier_id !== $user->id) {
            throw new AuthorizationException(__('crm.delivery.not_your_order'));
        }
    }

    private function notify(Order $order, DeliveryStatus $status, ?int $courierId = null): void
    {
        $this->realtime->send(new DeliveriesChanged($order->id, $status->value, $courierId));
    }
}
