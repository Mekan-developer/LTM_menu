<?php

namespace App\Services;

use App\Enums\DishKind;
use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Справочник продуктов и состав блюд (ТЗ 2.3, право «Калькуляция»). */
class CostingService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ActionLogger $logger,
    ) {}

    /** @param array<string, mixed> $data */
    public function createProduct(array $data): Product
    {
        $data['price'] = Money::toMinor($data['price']);

        return $this->products->create($data);
    }

    /**
     * Новая цена продукта сразу меняет себестоимость всех блюд с ним — пишем в журнал.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data, User $user): Product
    {
        $old = $product->price;
        $data['price'] = Money::toMinor($data['price']);

        $this->products->update($product, $data);

        if ($old !== $product->price) {
            $this->logger->log(ActionLogger::PRODUCT_PRICE_CHANGED, $product, [
                'name' => $product->name_ru, 'old' => $old, 'new' => $product->price, 'unit' => $product->unit->value,
            ], $user);
        }

        return $product;
    }

    /** Продукт из состава блюд не удаляем — сначала уберите его из рецептов. */
    public function deleteProduct(Product $product): void
    {
        $used = $product->ingredients()->count();

        if ($used > 0) {
            throw ValidationException::withMessages(['product' => __('crm.costing.product_in_use', ['n' => $used])]);
        }

        $this->products->delete($product);
    }

    /**
     * Сохранить состав блюда целиком. У готовой продукции — ровно один товар (обычно 1 шт).
     * Изменение рецепта пишется в журнал: рецепт влияет на списание со склада.
     *
     * @param  list<array{product_id: int, quantity: float|int|string}>  $lines
     */
    public function saveRecipe(Dish $dish, array $lines, User $user): void
    {
        if ($dish->kind === DishKind::Resale && count($lines) > 1) {
            throw ValidationException::withMessages(['lines' => __('crm.costing.resale_one')]);
        }

        $productIds = array_map(fn ($l) => (int) $l['product_id'], $lines);

        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['lines' => __('crm.costing.duplicate')]);
        }

        DB::transaction(function () use ($dish, $lines, $user) {
            $before = $this->snapshot($dish);

            $dish->ingredients()->delete();

            foreach ($lines as $line) {
                $dish->ingredients()->create([
                    'product_id' => (int) $line['product_id'],
                    'quantity' => round((float) $line['quantity'], 3),
                ]);
            }

            $dish->unsetRelation('ingredients');
            $after = $this->snapshot($dish);

            if ($before !== $after) {
                $this->logger->log(ActionLogger::RECIPE_CHANGED, $dish, [
                    'name' => $dish->name_ru, 'old' => $before, 'new' => $after,
                ], $user);
            }
        });
    }

    /**
     * Состав для журнала: «продукт → количество».
     *
     * @return array<string, float>
     */
    private function snapshot(Dish $dish): array
    {
        return $dish->ingredients()->with('product:id,name_ru')->get()
            ->mapWithKeys(fn (DishIngredient $i) => [($i->product->name_ru ?? '#'.$i->product_id) => $i->quantity])
            ->sortKeys()
            ->all();
    }
}
