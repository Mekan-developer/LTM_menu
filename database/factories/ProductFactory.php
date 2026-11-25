<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_tm' => 'Önüm '.fake()->unique()->numberBetween(1, 9999),
            'name_ru' => 'Продукт '.fake()->unique()->numberBetween(1, 9999),
            'unit' => ProductUnit::Kg->value,
            'price' => fake()->numberBetween(5, 200) * 100,
        ];
    }
}
