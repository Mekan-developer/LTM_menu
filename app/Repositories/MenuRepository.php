<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Collection;

class MenuRepository
{
    /**
     * Категории с блюдами для админки (включая неактивные).
     *
     * @return Collection<int, Category>
     */
    public function categoriesWithDishes(): Collection
    {
        return Category::query()
            ->with(['dishes' => fn ($q) => $q->with('stopEntry')->orderBy('sort')->orderBy('id'), 'stopEntry'])
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * Активные категории и блюда — для гостя и официанта.
     *
     * @return Collection<int, Category>
     */
    public function activeMenu(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->with([
                'dishes' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('id'),
            ])
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Dish>
     */
    public function activeDishesByIds(array $ids): Collection
    {
        return Dish::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->get()
            ->keyBy('id');
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    /** @param array<string, mixed> $data */
    public function createDish(array $data): Dish
    {
        return Dish::create($data);
    }

    /** @param array<string, mixed> $data */
    public function updateDish(Dish $dish, array $data): Dish
    {
        $dish->update($data);

        return $dish;
    }

    public function nextCategorySort(): int
    {
        return (int) Category::max('sort') + 1;
    }

    public function nextDishSort(int $categoryId): int
    {
        return (int) Dish::where('category_id', $categoryId)->max('sort') + 1;
    }
}
