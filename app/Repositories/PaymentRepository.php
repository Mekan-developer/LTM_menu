<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\Tip;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Действующие (не отменённые) оплаты за интервал.
     *
     * @return Collection<int, Payment>
     */
    public function validBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Payment::query()
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'order_id', 'method', 'cash_amount', 'card_amount', 'total', 'created_at']);
    }

    /** @return Collection<int, Payment> */
    public function recentForCashier(int $limit = 20): Collection
    {
        return Payment::query()
            ->with(['order:id,dining_table_id,channel', 'order.table:id,number', 'cashier:id,name'])
            ->where('created_at', '>=', now()->startOfDay())
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function createTip(array $data): Tip
    {
        return Tip::create($data);
    }

    /**
     * Чаевые за интервал; чаевые отменённой оплаты не считаются.
     *
     * @return Collection<int, Tip>
     */
    public function validTipsBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Tip::query()
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('payment_id')
                ->orWhereHas('payment', fn ($p) => $p->whereNull('cancelled_at')))
            ->get(['id', 'order_id', 'payment_id', 'user_id', 'amount', 'method', 'created_at']);
    }
}
