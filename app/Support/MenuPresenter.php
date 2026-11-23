<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Dish;
use App\Services\StopListService;
use Illuminate\Database\Eloquent\Collection;

/** Меню для гостя и официанта: обе локали сразу, флаг «в стопе». */
class MenuPresenter
{
    public function __construct(private readonly StopListService $stopList) {}

    /**
     * @param  bool  $hideStopped  гостю позиции в стопе не показываем вовсе (ТЗ 2.3)
     */
    public function present(Collection $categories, bool $hideStopped): array
    {
        $index = $this->stopList->index();

        return $categories
            ->map(function (Category $category) use ($index, $hideStopped) {
                $categoryStopped = isset($index['categories'][$category->id]);

                if ($hideStopped && $categoryStopped) {
                    return null;
                }

                $dishes = $category->dishes
                    ->map(fn (Dish $d) => [
                        'id' => $d->id,
                        'name_tm' => $d->name_tm,
                        'name_ru' => $d->name_ru,
                        'description_tm' => $d->description_tm,
                        'description_ru' => $d->description_ru,
                        'weight' => $d->weight,
                        'price' => $d->price,
                        'station' => $d->station->value,
                        'photo_url' => $d->photoUrl(),
                        'stopped' => $categoryStopped || isset($index['dishes'][$d->id]),
                    ])
                    ->when($hideStopped, fn ($c) => $c->reject(fn ($d) => $d['stopped']))
                    ->values()
                    ->all();

                if ($dishes === []) {
                    return null;
                }

                return [
                    'id' => $category->id,
                    'name_tm' => $category->name_tm,
                    'name_ru' => $category->name_ru,
                    'dishes' => $dishes,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** Версия меню: гость обновляет кэш в фоне, только если что-то изменилось. */
    public function version(array $menu): string
    {
        return substr(sha1(json_encode($menu)), 0, 12);
    }
}
