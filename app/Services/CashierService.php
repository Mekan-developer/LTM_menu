<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Events\CashierQueueChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\PaymentRepository;
use App\Support\Money;
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
    ) {}

    /** @param array{method:string, discount_percent?:int|null, cash_amount?:string|null, tip_amount?:string|null, tip_method?:string|null} $data */
    public function pay(Order $order, array $data, User $cashier): Payment
    {
        $percent = max(0, min(100, $data['discount_percent'] ?? 0));
        $this->assertDiscountAllowed($percent, $cashier);
        $tip = Money::toMinor($data['tip_amount'] ?? 0);

        if ($tip < 0) {
            throw ValidationException::withMessages(['tip_amount' => __('crm.cashier.tip_invalid')]);
        }

        $payment = DB::transaction(function () use ($order, $data, $percent, $cashier, $tip) {
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

            $method = PaymentMethod::from($data['method']);
            [$cash, $card] = match ($method) {
                PaymentMethod::Cash => [$total, 0],
                PaymentMethod::Card => [0, $total],
                PaymentMethod::Mixed => $this->splitMixed($total, Money::toMinor($data['cash_amount'] ?? 0)),
            };

            $payment = $this->payments->create([
                'order_id' => $order->id,
                'cashier_id' => $cashier->id,
                'method' => $method->value,
                'cash_amount' => $cash,
                'card_amount' => $card,
                'total' => $total,
            ]);

            $order->forceFill([
                'status' => OrderStatus::Paid->value,
                'paid_at' => now(),
                'closed_at' => now(),
            ])->save();

            // Одноразовая ссылка на отзыв (ТЗ 2.9).
            $this->reviews->createForOrder($order);

            // Чаевые — отдельно от выручки, привязаны к официанту счёта (ТЗ 2.12).
            if ($tip > 0) {
                $this->payments->createTip([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'user_id' => $order->waiter_id,
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

            $this->logger->log(ActionLogger::PAYMENT_CANCELLED, $payment, [
                'order_id' => $payment->order_id, 'total' => $payment->total, 'reason' => $reason,
            ], $user);
        });

        $this->realtime->send(new CashierQueueChanged($payment->order_id));
        $this->realtime->hallOf($payment->order);
    }

    /**
     * Итог за день: наличными / картой, отменённые оплаты не считаются; чаевые отдельно.
     *
     * @return array<string, mixed>
     */
    public function daySummary(): array
    {
        $payments = $this->payments->validBetween(now()->startOfDay(), now()->endOfDay());
        $tips = $this->payments->validTipsBetween(now()->startOfDay(), now()->endOfDay());

        return [
            'count' => $payments->count(),
            'cash' => (int) $payments->sum('cash_amount'),
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
