<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Tip;
use App\Repositories\PaymentRepository;
use App\Repositories\ReportRepository;
use App\Repositories\ReviewRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Отчёты за период (ТЗ 2.10). Деньги — в тенге целым числом.
 * Отчёты «Постоянные гости» и «Персонал» появятся вместе с базой гостей (2.1) и сменами (2.12);
 * каналы «доставка» и «самовывоз» — вместе с разделом 2.6. Пока вся выручка — зал.
 */
class ReportService
{
    public const MAX_DAYS = 366;

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly ReportRepository $reports,
        private readonly ReviewRepository $reviews,
    ) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function period(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays(6)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1)->startOfDay();
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'revenue' => $this->revenue($from, $to),
            'load' => $this->hallLoad($from, $to),
            'dishes' => $this->dishes($from, $to),
            'staff' => $this->staff($from, $to),
        ];
    }

    /**
     * Выручка и средний чек по дням + сравнение с прошлым периодом той же длины.
     *
     * @return array<string, mixed>
     */
    public function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $prevFrom = $from->subDays($days);
        $prevTo = $from->subSecond();

        $payments = $this->payments->validBetween($from, $to)->groupBy(fn (Payment $p) => $p->created_at->toDateString());
        $tips = $this->payments->validTipsBetween($from, $to)->groupBy(fn (Tip $t) => $t->created_at->toDateString());
        $previous = $this->payments->validBetween($prevFrom, $prevTo)->groupBy(fn (Payment $p) => $p->created_at->toDateString());

        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->toDateString();
            $prevDay = $prevFrom->addDays($i)->toDateString();
            $group = $payments->get($day, collect());
            $prevGroup = $previous->get($prevDay, collect());
            $revenue = (int) $group->sum('total');
            $bills = $group->count();
            $prevRevenue = (int) $prevGroup->sum('total');
            $prevBills = $prevGroup->count();

            $rows[] = [
                'date' => $day,
                'revenue' => $revenue,
                'bills' => $bills,
                'avg_check' => $bills > 0 ? intdiv($revenue, $bills) : 0,
                'cash' => (int) $group->sum('cash_amount'),
                'card' => (int) $group->sum('card_amount'),
                'tips' => (int) $tips->get($day, collect())->sum('amount'),
                'prev_date' => $prevDay,
                'prev_revenue' => $prevRevenue,
                'prev_avg_check' => $prevBills > 0 ? intdiv($prevRevenue, $prevBills) : 0,
            ];
        }

        $sum = fn (string $key) => (int) array_sum(array_column($rows, $key));
        $bills = $sum('bills');
        $prevRevenue = $sum('prev_revenue');
        $prevBills = (int) $previous->sum(fn ($group) => $group->count());
        $allTips = $this->payments->validTipsBetween($from, $to);

        return [
            'days' => $rows,
            'totals' => [
                'revenue' => $sum('revenue'),
                'bills' => $bills,
                'avg_check' => $bills > 0 ? intdiv($sum('revenue'), $bills) : 0,
                'cash' => $sum('cash'),
                'card' => $sum('card'),
                'tips' => $sum('tips'),
                'tips_cash' => (int) $allTips->where('method', PaymentMethod::Cash)->sum('amount'),
                'tips_card' => (int) $allTips->where('method', PaymentMethod::Card)->sum('amount'),
            ],
            'previous' => [
                'from' => $prevFrom->toDateString(),
                'to' => $prevTo->toDateString(),
                'revenue' => $prevRevenue,
                'bills' => $prevBills,
                'avg_check' => $prevBills > 0 ? intdiv($prevRevenue, $prevBills) : 0,
            ],
            // Доставка и самовывоз — раздел 2.6; пока все счета из зала.
            'channels' => ['hall' => $sum('revenue')],
        ];
    }

    /**
     * Загрузка зала: среднее число занятых столов по дню недели (1 = пн) и часу.
     *
     * @return array{matrix: list<list<float>>, max: float, tables: int}
     */
    public function hallLoad(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $now = CarbonImmutable::now();
        $busy = []; // "Y-m-d H" => [table_id => true]

        foreach ($this->reports->tableOccupancy($from, $to) as $row) {
            $start = CarbonImmutable::parse($row->opened_at)->max($from)->startOfHour();
            $end = CarbonImmutable::parse($row->closed_at ?? $now)->min($to);

            for ($h = $start; $h->lessThanOrEqualTo($end); $h = $h->addHour()) {
                $busy[$h->format('Y-m-d H')][$row->dining_table_id] = true;
            }
        }

        // Сколько раз каждый день недели встречается в периоде — делитель для среднего.
        $weekdays = array_fill(1, 7, 0);
        for ($d = $from->startOfDay(); $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
            $weekdays[$d->dayOfWeekIso]++;
        }

        /** @var array<int, array<int, int>> $sums */
        $sums = array_fill(1, 7, array_fill(0, 24, 0));
        foreach ($busy as $key => $tables) {
            $at = CarbonImmutable::createFromFormat('Y-m-d H', $key);

            if ($at === null) {
                continue;
            }

            $sums[$at->dayOfWeekIso][(int) $at->format('G')] += count($tables);
        }

        $matrix = [];
        $max = 0.0;
        foreach ($sums as $weekday => $hours) {
            $row = [];
            foreach ($hours as $count) {
                $value = $weekdays[$weekday] > 0 ? round($count / $weekdays[$weekday], 1) : 0.0;
                $row[] = $value;
                $max = max($max, $value);
            }
            $matrix[] = $row;
        }

        return ['matrix' => $matrix, 'max' => $max, 'tables' => $this->reports->tablesCount()];
    }

    /**
     * Самые продаваемые и самые редкие блюда. Себестоимость и наценка — с разделом «Калькуляция».
     *
     * @return array{top: array<int, array<string, mixed>>, rare: array<int, array<string, mixed>>}
     */
    public function dishes(CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        $sales = $this->reports->dishSales($from, $to);
        $rows = $sales->map(fn ($r) => [
            'dish_id' => $r->dish_id,
            'name_tm' => $r->name_tm,
            'name_ru' => $r->name_ru,
            'qty' => (int) $r->qty,
            'amount' => (int) $r->amount,
        ]);

        // Блюда из меню без единой продажи — тоже «редкие».
        $sold = $rows->pluck('dish_id')->filter()->all();
        $unsold = $this->reports->activeDishes()
            ->reject(fn ($d) => in_array($d->id, $sold, true))
            ->map(fn ($d) => ['dish_id' => $d->id, 'name_tm' => $d->name_tm, 'name_ru' => $d->name_ru, 'qty' => 0, 'amount' => 0]);

        return [
            'top' => $rows->sortByDesc('qty')->take($limit)->values()->all(),
            'rare' => $rows->filter(fn ($r) => $r['dish_id'] !== null)->concat($unsold)
                ->sortBy('qty')->take($limit)->values()->all(),
        ];
    }

    /**
     * По каждому официанту: счета, выручка, средний чек, оценка в отзывах, удалённые блюда, чаевые.
     *
     * @return array<int, array<string, mixed>>
     */
    public function staff(CarbonInterface $from, CarbonInterface $to): array
    {
        $revenue = $this->reports->revenueByWaiter($from, $to)->keyBy('waiter_id');
        $removed = $this->reports->removedByWaiter($from, $to)->keyBy('waiter_id');
        $ratings = $this->reviews->byWaiter($from, $to)->keyBy('waiter_id');
        $tips = $this->payments->validTipsBetween($from, $to)->whereNotNull('user_id')->groupBy('user_id');

        $ids = collect([...$revenue->keys(), ...$removed->keys(), ...$ratings->keys(), ...$tips->keys()])
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        /** @var list<int> $ids */
        $names = $this->reports->userNames($ids);

        return collect($ids)->map(function (int $id) use ($revenue, $removed, $ratings, $tips, $names) {
            $bills = (int) ($revenue->get($id)->bills ?? 0);
            $sum = (int) ($revenue->get($id)->revenue ?? 0);
            $rating = $ratings->get($id);

            return [
                'id' => $id,
                'name' => $names->get($id, '—'),
                'bills' => $bills,
                'revenue' => $sum,
                'avg_check' => $bills > 0 ? intdiv($sum, $bills) : 0,
                'avg_rating' => $rating ? round((float) $rating->avg_rating, 1) : null,
                'reviews' => (int) ($rating->reviews ?? 0),
                'removed' => (int) ($removed->get($id)->removed ?? 0),
                'tips' => (int) $tips->get($id, collect())->sum('amount'),
            ];
        })->sortByDesc('revenue')->values()->all();
    }
}
