<?php

namespace App\Services;

use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;

/** Выгрузка отчётов в Excel (ТЗ 2.10). Заголовки — на языке интерфейса. */
class ReportExporter
{
    public const REPORTS = ['revenue', 'guests', 'load', 'dishes', 'staff'];

    public function __construct(private readonly ReportService $reports) {}

    /** @param  bool  $withPhones  телефоны гостей — только с правом «Гости» */
    public function export(string $report, CarbonImmutable $from, CarbonImmutable $to, bool $withPhones = false): XlsxWriter
    {
        return match ($report) {
            'revenue' => $this->revenue($from, $to),
            'guests' => $this->guests($from, $to, $withPhones),
            'load' => $this->load($from, $to),
            'dishes' => $this->dishes($from, $to),
            default => $this->staff($from, $to),
        };
    }

    public function filename(string $report, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return sprintf('%s_%s_%s.xlsx', $report, $from->format('Y-m-d'), $to->format('Y-m-d'));
    }

    private function revenue(CarbonImmutable $from, CarbonImmutable $to): XlsxWriter
    {
        $data = $this->reports->revenue($from, $to);
        $xlsx = (new XlsxWriter(__('crm.reports.revenue')))
            ->setWidths([12, 14, 10, 14, 14, 14, 12, 14])
            ->addHeader([
                __('crm.reports.date'), __('crm.reports.revenue'), __('crm.reports.bills'), __('crm.reports.avg_check'),
                __('crm.reports.cash'), __('crm.reports.card'), __('crm.reports.tips'), __('crm.reports.prev_revenue'),
            ]);

        foreach ($data['days'] as $d) {
            $xlsx->addRow([
                CarbonImmutable::parse($d['date'])->format('d.m.Y'),
                self::money($d['revenue']), $d['bills'], self::money($d['avg_check']),
                self::money($d['cash']), self::money($d['card']), self::money($d['tips']), self::money($d['prev_revenue']),
            ]);
        }

        $t = $data['totals'];

        $xlsx->addRow([
            ['v' => __('crm.reports.total'), 's' => 'bold'],
            self::money($t['revenue']), $t['bills'], self::money($t['avg_check']),
            self::money($t['cash']), self::money($t['card']), self::money($t['tips']), self::money($data['previous']['revenue']),
        ]);

        // Выручка по каналам (ТЗ 2.10).
        $xlsx->addRow([])->addRow([['v' => __('crm.reports.by_channel'), 's' => 'bold']]);
        foreach ($data['channels'] as $channel => $amount) {
            $xlsx->addRow([__('crm.reports.channel_'.$channel), self::money($amount)]);
        }

        return $xlsx;
    }

    private function guests(CarbonImmutable $from, CarbonImmutable $to, bool $withPhones): XlsxWriter
    {
        $data = $this->reports->guests($from, $to, $withPhones);
        $t = $data['totals'];
        $xlsx = (new XlsxWriter(__('crm.reports.guests')))
            ->setWidths([28, 18, 10, 14, 14, 14, 10])
            ->addRow([__('crm.reports.guests_total'), $t['guests']])
            ->addRow([__('crm.reports.guests_new'), $t['new']])
            ->addRow([__('crm.reports.guests_returning'), $t['returning']])
            ->addRow([__('crm.reports.bills_with_guest'), $t['bills_with_guest'].' / '.$t['bills']])
            ->addRow([])
            ->addRow([['v' => __('crm.reports.guests_top'), 's' => 'bold']])
            ->addHeader([
                __('crm.guests.name'), __('crm.guests.phone'), __('crm.guests.visits'), __('crm.guests.spent'),
                __('crm.reports.avg_check'), __('crm.guests.last_visit'), __('crm.reports.guests_new'),
            ]);

        foreach ($data['top'] as $g) {
            $xlsx->addRow([
                $g['name'], $g['phone'] ?? '—', $g['visits'], self::money($g['spent']), self::money($g['avg_check']),
                CarbonImmutable::parse($g['last_at'])->format('d.m.Y'), $g['is_new'] ? '✓' : '',
            ]);
        }

        return $xlsx;
    }

    private function load(CarbonImmutable $from, CarbonImmutable $to): XlsxWriter
    {
        $data = $this->reports->hallLoad($from, $to);
        $xlsx = (new XlsxWriter(__('crm.reports.load')))
            ->setWidths([10, ...array_fill(0, 24, 6)])
            ->addHeader([__('crm.reports.weekday'), ...array_map(fn ($h) => sprintf('%02d', $h), range(0, 23))]);

        $days = __('crm.reports.weekdays');
        foreach ($data['matrix'] as $i => $hours) {
            $xlsx->addRow([is_array($days) ? $days[$i] : $i + 1, ...array_map(fn ($v) => ['v' => $v, 's' => 'decimal'], $hours)]);
        }

        return $xlsx;
    }

    private function dishes(CarbonImmutable $from, CarbonImmutable $to): XlsxWriter
    {
        $data = $this->reports->dishes($from, $to, 50);
        $name = app()->getLocale() === 'ru' ? 'name_ru' : 'name_tm';
        $xlsx = (new XlsxWriter(__('crm.reports.dishes')))
            ->setWidths([34, 10, 14])
            ->addRow([['v' => __('crm.reports.top_dishes'), 's' => 'bold']])
            ->addHeader([__('crm.reports.dish'), __('crm.reports.qty'), __('crm.reports.amount')]);

        foreach ($data['top'] as $d) {
            $xlsx->addRow([$d[$name], $d['qty'], self::money($d['amount'])]);
        }

        $xlsx->addRow([])->addRow([['v' => __('crm.reports.rare_dishes'), 's' => 'bold']])
            ->addHeader([__('crm.reports.dish'), __('crm.reports.qty'), __('crm.reports.amount')]);

        foreach ($data['rare'] as $d) {
            $xlsx->addRow([$d[$name], $d['qty'], self::money($d['amount'])]);
        }

        return $xlsx;
    }

    private function staff(CarbonImmutable $from, CarbonImmutable $to): XlsxWriter
    {
        $xlsx = (new XlsxWriter(__('crm.reports.staff')))
            ->setWidths([24, 10, 14, 14, 12, 10, 12, 12])
            ->addHeader([
                __('crm.reports.waiter'), __('crm.reports.bills'), __('crm.reports.revenue'), __('crm.reports.avg_check'),
                __('crm.reports.avg_rating'), __('crm.reports.reviews'), __('crm.reports.removed'), __('crm.reports.tips'),
            ]);

        foreach ($this->reports->staff($from, $to) as $s) {
            $xlsx->addRow([
                $s['name'], $s['bills'], self::money($s['revenue']), self::money($s['avg_check']),
                $s['avg_rating'] === null ? null : ['v' => $s['avg_rating'], 's' => 'decimal'],
                $s['reviews'], $s['removed'], self::money($s['tips']),
            ]);
        }

        return $xlsx;
    }

    /** @return array{v: float, s: string} */
    private static function money(int $minor): array
    {
        return ['v' => $minor / 100, 's' => 'money'];
    }
}
