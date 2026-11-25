<?php

namespace App\Support;

use App\Enums\StopMode;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Dish;
use App\Models\StopListEntry;
use App\Repositories\MenuRepository;
use App\Repositories\StopListRepository;

/** Данные стоп-листа: что сейчас в стопе и что можно поставить. Страница стоп-листа и панель на экране кухни. */
class StopListScreen
{
    public function __construct(
        private readonly StopListRepository $stopList,
        private readonly MenuRepository $menu,
    ) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [
            'entries' => $this->stopList->active()->map(function (StopListEntry $e) {
                $named = $e->stoppable instanceof Dish || $e->stoppable instanceof Category ? $e->stoppable : null;

                return [
                    'id' => $e->id,
                    'type' => $e->stoppable_type,
                    'name_tm' => $named->name_tm ?? '—',
                    'name_ru' => $named->name_ru ?? '—',
                    'mode' => $e->mode->value,
                    'until' => $e->until?->toIso8601String(),
                    'author' => $e->author?->name,
                    'created_at' => $e->created_at?->toIso8601String(),
                ];
            })->values(),
            'categories' => CategoryResource::collection($this->menu->categoriesWithDishes()),
            'modes' => array_map(fn (StopMode $m) => $m->value, StopMode::cases()),
        ];
    }
}
