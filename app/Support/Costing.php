<?php

namespace App\Support;

use App\Models\Dish;
use App\Models\DishIngredient;

/**
 * Себестоимость порции, наценка и доля себестоимости в цене (ТЗ 2.3).
 * Считается на лету по текущим ценам продуктов — при смене цены продукта пересчитываются все блюда с ним.
 * Нужны загруженные ingredients.product.
 */
final class Costing
{
    /** @return array{cost: int, markup: ?float, share: ?float, has_recipe: bool} */
    public static function of(Dish $dish): array
    {
        $cost = (int) $dish->ingredients->sum(fn (DishIngredient $i) => $i->cost());
        $price = $dish->price;

        return [
            'cost' => $cost,
            // Наценка: на сколько % цена выше себестоимости.
            'markup' => $cost > 0 ? round(($price - $cost) / $cost * 100, 1) : null,
            // Доля себестоимости в цене.
            'share' => $price > 0 ? round($cost / $price * 100, 1) : null,
            'has_recipe' => $dish->ingredients->isNotEmpty(),
        ];
    }
}
