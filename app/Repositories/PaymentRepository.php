<?php

namespace App\Repositories;

use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    /** Действующие (не отменённые) оплаты за интервал. */
    public function validBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Payment::query()
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'order_id', 'method', 'cash_amount', 'card_amount', 'total', 'created_at']);
    }

    public function recentForCashier(int $limit = 20): Collection
    {
        return Payment::query()
            ->with(['order:id,dining_table_id', 'order.table:id,number', 'cashier:id,name'])
            ->where('created_at', '>=', now()->startOfDay())
            ->latest()
            ->limit($limit)
            ->get();
    }
}
