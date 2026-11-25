<?php

namespace App\Services;

use App\Models\Dish;
use App\Support\Costing;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Collection;

/** Калькуляция в Excel (ТЗ 2.3): карточка одного блюда или сводка по всему меню. */
class CostingExporter
{
    public function dish(Dish $dish): XlsxWriter
    {
        $c = Costing::of($dish);
        $xlsx = (new XlsxWriter(__('crm.costing.title')))
            ->setWidths([34, 12, 10, 16, 14])
            ->addRow([['v' => $dish->localizedName(), 's' => 'bold']])
            ->addRow([$dish->category?->localizedName() ?? ''])
            ->addRow([])
            ->addHeader([
                __('crm.costing.product'), __('crm.costing.quantity'), __('crm.costing.unit'),
                __('crm.costing.unit_price'), __('crm.costing.line_cost'),
            ]);

        foreach ($dish->ingredients as $i) {
            $xlsx->addRow([
                $i->product?->localizedName() ?? '—',
                ['v' => $i->quantity, 's' => 'decimal'],
                $i->product ? __('crm.costing.unit_'.$i->product->unit->value) : '',
                self::money($i->product->price ?? 0),
                self::money($i->cost()),
            ]);
        }

        return $xlsx->addRow([])
            ->addRow([['v' => __('crm.costing.cost'), 's' => 'bold'], '', '', '', self::money($c['cost'])])
            ->addRow([__('crm.costing.price'), '', '', '', self::money($dish->price)])
            ->addRow([__('crm.costing.markup'), '', '', '', self::percent($c['markup'])])
            ->addRow([__('crm.costing.share'), '', '', '', self::percent($c['share'])]);
    }

    /** @param Collection<int, Dish> $dishes */
    public function all(Collection $dishes): XlsxWriter
    {
        $xlsx = (new XlsxWriter(__('crm.costing.title')))
            ->setWidths([22, 34, 14, 14, 12, 12, 10])
            ->addHeader([
                __('crm.costing.category'), __('crm.costing.dish'), __('crm.costing.price'), __('crm.costing.cost'),
                __('crm.costing.markup'), __('crm.costing.share'), __('crm.costing.lines'),
            ]);

        foreach ($dishes as $dish) {
            $c = Costing::of($dish);
            $xlsx->addRow([
                $dish->category?->localizedName() ?? '',
                $dish->localizedName(),
                self::money($dish->price),
                $c['has_recipe'] ? self::money($c['cost']) : '—',
                self::percent($c['markup']),
                self::percent($c['share']),
                $dish->ingredients->count(),
            ]);
        }

        return $xlsx;
    }

    /** @return array{v: float, s: string} */
    private static function money(int $minor): array
    {
        return ['v' => $minor / 100, 's' => 'money'];
    }

    /** @return array{v: float, s: string}|string */
    private static function percent(?float $value): array|string
    {
        return $value === null ? '—' : ['v' => $value, 's' => 'decimal'];
    }
}
