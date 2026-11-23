<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Dish;
use App\Models\StopListEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StopListRepository
{
    public function active(): Collection
    {
        return StopListEntry::query()
            ->active()
            ->with(['stoppable', 'author:id,name'])
            ->latest()
            ->get();
    }

    /**
     * Множества id блюд и категорий, которые сейчас в стопе.
     *
     * @return array{dishes: array<int, true>, categories: array<int, true>}
     */
    public function activeIndex(): array
    {
        $index = ['dishes' => [], 'categories' => []];

        StopListEntry::query()->active()->get(['stoppable_type', 'stoppable_id'])
            ->each(function (StopListEntry $e) use (&$index) {
                $key = $e->stoppable_type === 'category' ? 'categories' : 'dishes';
                $index[$key][(int) $e->stoppable_id] = true;
            });

        return $index;
    }

    public function upsert(Model $stoppable, array $data): StopListEntry
    {
        return StopListEntry::updateOrCreate(
            ['stoppable_type' => $stoppable->getMorphClass(), 'stoppable_id' => $stoppable->getKey()],
            $data,
        );
    }

    public function findStoppable(string $type, int $id): Dish|Category
    {
        return $type === 'category' ? Category::findOrFail($id) : Dish::findOrFail($id);
    }
}
