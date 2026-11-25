<?php

namespace App\Services;

use App\Enums\BonusType;
use App\Models\BonusTransaction;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Бонусная программа (ТЗ 2.8). 1 бонус = 1 TMT, суммы в тенге.
 * Начисление: N % с суммы, оплаченной деньгами (после скидки, без части бонусами).
 * Списание: не больше M % счёта и не больше баланса; скидка и бонусы не суммируются.
 * Подтверждение списания SMS-кодом появится вместе с SMS-шлюзом — сейчас кассир находит гостя по телефону.
 */
class BonusService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActionLogger $logger,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('bonus_enabled');
    }

    /** @return array{enabled: bool, accrual_percent: int, max_spend_percent: int} */
    public function rules(): array
    {
        return [
            'enabled' => $this->enabled(),
            'accrual_percent' => (int) $this->settings->get('bonus_accrual_percent'),
            'max_spend_percent' => (int) $this->settings->get('bonus_max_spend_percent'),
        ];
    }

    /** Сколько можно списать с этого счёта: не больше M % суммы и не больше баланса. */
    public function maxSpend(Guest $guest, int $billTotal): int
    {
        $byPercent = intdiv($billTotal * (int) $this->settings->get('bonus_max_spend_percent'), 100);

        return max(0, min($guest->bonus_balance, $byPercent));
    }

    public function accrualFor(int $paidWithMoney): int
    {
        return intdiv(max(0, $paidWithMoney) * (int) $this->settings->get('bonus_accrual_percent'), 100);
    }

    /** Проверить списание до проведения оплаты. Вызывается внутри транзакции оплаты. */
    public function assertCanSpend(?Guest $guest, int $amount, int $billTotal, int $discountPercent): void
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['bonus_amount' => __('crm.bonus.disabled')]);
        }

        if ($guest === null) {
            throw ValidationException::withMessages(['bonus_amount' => __('crm.bonus.guest_required')]);
        }

        if ($discountPercent > 0) {
            throw ValidationException::withMessages(['bonus_amount' => __('crm.bonus.no_combine')]);
        }

        if ($amount > $this->maxSpend($guest, $billTotal)) {
            throw ValidationException::withMessages(['bonus_amount' => __('crm.bonus.too_much', [
                'max' => number_format($this->maxSpend($guest, $billTotal) / 100, 2, ',', ' '),
            ])]);
        }
    }

    public function spend(Guest $guest, Order $order, Payment $payment, int $amount, User $user): void
    {
        $this->post($guest, BonusType::Spend, -$amount, $order, $payment, $user);

        $this->logger->log(ActionLogger::BONUS_SPENT, $order, [
            'guest' => $guest->name, 'amount' => $amount, 'payment_id' => $payment->id,
        ], $user);
    }

    /** Начислить с оплаченной деньгами суммы. Программа выключена — ничего не начисляем. */
    public function accrue(Guest $guest, Order $order, Payment $payment, User $user): int
    {
        $amount = $this->enabled() ? $this->accrualFor($payment->total) : 0;

        if ($amount > 0) {
            $this->post($guest, BonusType::Accrual, $amount, $order, $payment, $user);
        }

        return $amount;
    }

    /**
     * Отмена оплаты: начисленные за счёт бонусы снимаются, списанные — возвращаются (ТЗ 2.8).
     * Работает и при выключенной программе — баланс должен остаться честным.
     */
    public function reversePayment(Payment $payment, User $user): void
    {
        $operations = BonusTransaction::query()->where('payment_id', $payment->id)
            ->whereIn('type', [BonusType::Accrual->value, BonusType::Spend->value])
            ->orderBy('id')->get();

        $alreadyReversed = BonusTransaction::query()->where('payment_id', $payment->id)
            ->whereIn('type', [BonusType::AccrualReversal->value, BonusType::SpendReversal->value])->exists();

        if ($alreadyReversed) {
            return;
        }

        foreach ($operations as $op) {
            $guest = Guest::query()->find($op->guest_id);

            if ($guest === null) {
                continue;
            }

            $type = $op->type === BonusType::Accrual ? BonusType::AccrualReversal : BonusType::SpendReversal;
            $this->post($guest, $type, -$op->amount, null, $payment, $user, $op->order_id);
        }
    }

    /** @return Collection<int, BonusTransaction> */
    public function history(Guest $guest, int $limit = 50): Collection
    {
        return $guest->bonusTransactions()->with('user:id,name')->orderByDesc('id')->limit($limit)->get();
    }

    private function post(Guest $guest, BonusType $type, int $amount, ?Order $order, Payment $payment, User $user, ?int $orderId = null): void
    {
        $locked = Guest::query()->lockForUpdate()->findOrFail($guest->id);
        $balance = $locked->bonus_balance + $amount;
        $locked->forceFill(['bonus_balance' => $balance])->save();
        $guest->bonus_balance = $balance;

        BonusTransaction::query()->create([
            'guest_id' => $guest->id,
            'order_id' => $order->id ?? $orderId ?? $payment->order_id,
            'payment_id' => $payment->id,
            'type' => $type->value,
            'amount' => $amount,
            'balance_after' => $balance,
            'user_id' => $user->id,
        ]);
    }
}
