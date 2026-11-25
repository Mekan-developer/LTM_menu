<?php

namespace App\Repositories;

use App\Models\Dish;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * Справочник продуктов с числом блюд, где продукт есть в составе.
     *
     * @return Collection<int, Product>
     */
    public function all(?string $search = null): Collection
    {
        return Product::query()
            ->withCount('ingredients as dishes_count')
            ->when(filled($search), function (Builder $q) use ($search) {
                $term = '%'.mb_strtolower(trim((string) $search)).'%';
                $q->where(fn (Builder $w) => $w->whereRaw('lower(name_ru) like ?', [$term])
                    ->orWhereRaw('lower(name_tm) like ?', [$term]));
            })
            ->orderBy('name_ru')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * Все блюда с составом — для экрана калькуляции.
     *
     * @return Collection<int, Dish>
     */
    public function dishesWithRecipes(): Collection
    {
        return Dish::query()
            ->with(['category:id,name_tm,name_ru,sort', 'ingredients.product'])
            ->join('categories', 'categories.id', '=', 'dishes.category_id')
            ->orderBy('categories.sort')->orderBy('categories.id')
            ->orderBy('dishes.sort')->orderBy('dishes.id')
            ->select('dishes.*')
            ->get();
    }
}
