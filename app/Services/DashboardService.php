<?php

namespace App\Services;

use App\Models\Payment;
use App\Repositories\BookingRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ReportRepository;

/** Главная админки (ТЗ 2.10): выручка, счета, средний чек, брони, занятые столы за сегодня; выручка за 7 дней. */
class DashboardService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly ReportRepository $reports,
        private readonly BookingRepository $bookings,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $today = now()->startOfDay();
        $todayPayments = $this->payments->validBetween($today, now()->endOfDay());

        // Сравнение с тем же днём прошлой недели до текущего часа.
        $lastWeek = $this->payments->validBetween($today->copy()->subWeek(), now()->subWeek());

        $revenue = (int) $todayPayments->sum('total');
        $bills = $todayPayments->count();

        // 7 дней + те же 7 дней неделей раньше, группировка в PHP — объёмы небольшие.
        $period = $this->payments->validBetween($today->copy()->subDays(13), now()->endOfDay())
            ->groupBy(fn (Payment $p) => $p->created_at->toDateString())
            ->map(fn ($group) => (int) $group->sum('total'));

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $series[] = [
                'date' => $day->toDateString(),
                'revenue' => $period->get($day->toDateString(), 0),
                'previous' => $period->get($day->copy()->subWeek()->toDateString(), 0),
            ];
        }

        return [
            'today' => [
                'revenue' => $revenue,
                'revenue_last_week' => (int) $lastWeek->sum('total'),
                'bills' => $bills,
                'avg_check' => $bills > 0 ? intdiv($revenue, $bills) : 0,
                'cash' => (int) $todayPayments->sum('cash_amount'),
                'card' => (int) $todayPayments->sum('card_amount'),
                'open_tables' => $this->reports->activeOrdersCount(),
                'tables' => $this->reports->tablesCount(),
                'bookings' => $this->bookings->countForDay($today), // брони на сегодня (ТЗ 2.10)
            ],
            'series' => $series,
            'top_dishes' => $this->reports->topDishes($today, now()->endOfDay())->map(fn ($r) => [
                'name_tm' => $r->name_tm,
                'name_ru' => $r->name_ru,
                'qty' => (int) $r->qty,
                'amount' => (int) $r->amount,
            ])->all(),
        ];
    }
}
