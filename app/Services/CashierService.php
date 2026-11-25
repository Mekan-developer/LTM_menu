<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Events\CashierQueueChanged;
use App\Events\DeliveriesChanged;
use App\Events\KitchenBoardChanged;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\DeliveryRepository;
use App\Repositories\PaymentRepository;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Касса (ТЗ 2.7): оплата наличными, картой или частями; разовая скидка; отмена оплаты. */
class CashierService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly OrderCalculator $calculator,
        private readonly SettingsService $settings,
        private readonly ActionLogger $logger,
        private readonly Realtime $realtime,
        private readonly ReviewService $reviews,
        private readonly GuestService $guests,
        private readonly DeliveryRepository $deliveries,
        private readonly StockService $stock,
        private readonly BonusService $bonuses,
    ) {}

    /**
     * Оплата на кассе. Доставку оплачивает курьер при вручении (DeliveryService::complete),
     * самовывоз — здесь же, когда заказ готов: после оплаты он сразу «выдан» (ТЗ 2.6).
     *
     * @param  array{method:string, discount_percent?:int|null, cash_amount?:string|null, bonus_amount?:string|null, tip_amount?:string|null, tip_method?:string|null, guest_phone?:string|null, guest_name?:string|null}  $data
     */
    public function pay(Order $order, array $data, User $cashier): Payment
    {
        if ($order->channel === OrderChannel::Delivery) {
            throw ValidationException::withMessages(['order' => __('crm.delivery.courier_pays')]);
        }

        if ($order->channel !== OrderChannel::Pickup) {
            return $this->settle($order, $data, $cashier);
        }

        $payment = DB::transaction(function () use ($order, $data, $cashier) {
            $delivery = $this->deliveries->lockForOrder($order->id);

            if ($delivery === null || $delivery->status !== DeliveryStatus::Ready) {
                throw ValidationException::withMessages(['order' => __('crm.delivery.not_ready')]);
            }

            $payment = $this->settle($order, $data, $cashier);
            $delivery->forceFill(['status' => DeliveryStatus::Issued->value, 'finished_at' => now()])->save();
            // Самовывоз выдан — с экрана кухни «Ждут выдачи» заказ уходит.
            $order->items()->where('status', OrderItemStatus::Ready->value)
                ->update(['status' => OrderItemStatus::Served->value, 'served_at' => now()]);

            return $payment;
        });

        $this->realtime->send(new DeliveriesChanged($order->id, DeliveryStatus::Issued->value));
        $this->realtime->send(new KitchenBoardChanged(['kitchen', 'bar']));

        return $payment;
    }

    /**
     * Провести оплату заказа: скидка, способ оплаты, чаевые, гость, ссылка на отзыв.
     * Общая для кассы и курьера. Чаевые — официанту счёта или курьеру ($tipUserId).
     *
     * @param  array{method:string, discount_percent?:int|null, cash_amount?:string|null, bonus_amount?:string|null, tip_amount?:string|null, tip_method?:string|null, guest_phone?:string|null, guest_name?:string|null}  $data
     */
    public function settle(Order $order, array $data, User $cashier, ?int $tipUserId = null): Payment
    {
        $percent = max(0, min(100, $data['discount_percent'] ?? 0));
        $this->assertDiscountAllowed($percent, $cashier);
        $tip = Money::toMinor($data['tip_amount'] ?? 0);

        if ($tip < 0) {
            throw ValidationException::withMessages(['tip_amount' => __('crm.cashier.tip_invalid')]);
        }

        $guestPhone = $data['guest_phone'] ?? null;
        if (filled($guestPhone) && Phone::normalize($guestPhone) === null) {
            throw ValidationException::withMessages(['guest_phone' => __('crm.guests.phone_invalid')]);
        }

        $payment = DB::transaction(function () use ($order, $data, $percent, $cashier, $tip, $guestPhone, $tipUserId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isActive()) {
                throw ValidationException::withMessages(['order' => __('crm.cashier.not_active')]);
            }

            if ($order->hasUnsentItems()) {
                throw ValidationException::withMessages(['order' => __('crm.cashier.unsent_items')]);
            }

            $order->discount_percent = $percent;
            $this->calculator->recalculate($order);
            $total = $order->total;

            if ($total <= 0) {
                throw ValidationException::withMessages(['order' => __('crm.cashier.empty')]);
            }

            // Гость назвал телефон — счёт попадает в его историю (ТЗ 2.1). Иначе — гость из доставки или брони.
            $guest = filled($guestPhone)
                ? $this->guests->attachToOrder($order, (string) $guestPhone, $data['guest_name'] ?? null, $cashier)
                : ($order->guest_id ? Guest::query()->find($order->guest_id) : null);

            // Часть счёта бонусами (ТЗ 2.8): остальное — деньгами.
            $bonus = Money::toMinor($data['bonus_amount'] ?? 0);
            if ($bonus > 0) {
                $this->bonuses->assertCanSpend($guest, $bonus, $total, $percent);
            }
            $money = $total - $bonus;

            $method = PaymentMethod::from($data['method']);
            [$cash, $card] = match (true) {
                $money === 0 => [0, 0], // весь счёт бонусами
                $method === PaymentMethod::Cash => [$money, 0],
                $method === PaymentMethod::Card => [0, $money],
                default => $this->splitMixed($money, Money::toMinor($data['cash_amount'] ?? 0)),
            };

            $payment = $this->payments->create([
                'order_id' => $order->id,
                'cashier_id' => $cashier->id,
                'method' => $method->value,
                'cash_amount' => $cash,
                'card_amount' => $card,
                'bonus_amount' => $bonus,
                'total' => $money,
            ]);

            $order->forceFill([
                'status' => OrderStatus::Paid->value,
                'bonus_paid' => $bonus,
                'paid_at' => now(),
                'closed_at' => now(),
            ])->save();

            // Продукты списываются со склада по составу блюд (ТЗ 2.3).
            $this->stock->consumeOrder($order, $cashier);

            if ($guest !== null) {
                if ($bonus > 0) {
                    $this->bonuses->spend($guest, $order, $payment, $bonus, $cashier);
                }
                $payment->forceFill(['bonus_accrued' => $this->bonuses->accrue($guest, $order, $payment, $cashier)])->save();
            }

            // Одноразовая ссылка на отзыв (ТЗ 2.9).
            $this->reviews->createForOrder($order);

            // Чаевые — отдельно от выручки, привязаны к официанту счёта (ТЗ 2.12).
            if ($tip > 0) {
                $this->payments->createTip([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'user_id' => $tipUserId ?? $order->waiter_id,
                    'amount' => $tip,
                    'method' => ($data['tip_method'] ?? 'cash') === 'card' ? 'card' : 'cash',
                    'created_by' => $cashier->id,
                ]);
            }

            if ($percent > 0) {
                $this->logger->log(ActionLogger::DISCOUNT_APPLIED, $order, [
                    'percent' => $percent, 'amount' => $order->discount_amount,
                ], $cashier);
            }

            return $payment;
        });

        $this->realtime->send(new CashierQueueChanged($order->id));
        $this->realtime->hallOf($order);

        return $payment;
    }

    /** Отмена уже оплаченного счёта (возврат) — право «Отмена оплаты», с причиной. */
    public function cancel(Payment $payment, string $reason, User $user): void
    {
        if (! $user->hasPermission(Permission::CancelPayment)) {
            throw new AuthorizationException(__('crm.errors.forbidden'));
        }

        if ($payment->cancelled_at !== null) {
            throw ValidationException::withMessages(['payment' => __('crm.cashier.already_cancelled')]);
        }

        DB::transaction(function () use ($payment, $reason, $user) {
            $payment->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ])->save();

            $payment->order()->update(['status' => OrderStatus::Refunded->value]);
            // Списанное по счёту возвращается на склад; испорченное — ручным списанием.
            $this->stock->restoreOrder($payment->order, $user);
            // Начисленные бонусы снимаются, списанные — возвращаются гостю (ТЗ 2.8).
            $this->bonuses->reversePayment($payment, $user);

            $this->logger->log(ActionLogger::PAYMENT_CANCELLED, $payment, [
                'order_id' => $payment->order_id, 'total' => $payment->total, 'reason' => $reason,
            ], $user);
        });

        $this->realtime->send(new CashierQueueChanged($payment->order_id));
        $this->realtime->hallOf($payment->order);
    }

    /**
     * Итог за день: наличными в кассе / картой / наличными у курьеров, отменённые оплаты не считаются; чаевые отдельно.
     *
     * @return array<string, mixed>
     */
    public function daySummary(): array
    {
        $payments = $this->payments->validBetween(now()->startOfDay(), now()->endOfDay());
        $tips = $this->payments->validTipsBetween(now()->startOfDay(), now()->endOfDay());
        // Наличные за доставку, которые курьеры ещё не сдали, — отдельной строкой «у курьера» (ТЗ 2.6).
        $onCouriers = (int) $this->deliveries->cashOnCouriers(now()->startOfDay())->sum('amount');

        return [
            'count' => $payments->count(),
            'cash' => (int) $payments->sum('cash_amount') - $onCouriers,
            'courier_cash' => $onCouriers,
            'card' => (int) $payments->sum('card_amount'),
            'total' => (int) $payments->sum('total'),
            // Чаевые не входят в выручку — отдельной строкой.
            'tips_cash' => (int) $tips->where('method', PaymentMethod::Cash)->sum('amount'),
            'tips_card' => (int) $tips->where('method', PaymentMethod::Card)->sum('amount'),
        ];
    }

    public function discountLimit(): int
    {
        return (int) $this->settings->get('discount_limit_k');
    }

    /** Разовая скидка до K % — право «Скидка до K %», больше — «Скидка без лимита» (ТЗ 2.8). */
    private function assertDiscountAllowed(int $percent, User $cashier): void
    {
        if ($percent <= 0) {
            return;
        }

        $allowed = $percent > $this->discountLimit()
            ? $cashier->hasPermission(Permission::DiscountUnlimited)
            : $cashier->hasPermission(Permission::DiscountUpToK) || $cashier->hasPermission(Permission::DiscountUnlimited);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'discount_percent' => __('crm.cashier.discount_limit', ['k' => $this->discountLimit()]),
            ]);
        }
    }

    /**
     * Наличными — сколько ввёл кассир, картой — остаток автоматически.
     *
     * @return array{int, int}
     */
    private function splitMixed(int $total, int $cash): array
    {
        if ($cash <= 0 || $cash >= $total) {
            throw ValidationException::withMessages(['cash_amount' => __('crm.cashier.mixed_invalid')]);
        }

        return [$cash, $total - $cash];
    }
}
