<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dish> */
class DishFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name_tm' => 'Nahar '.fake()->unique()->numberBetween(1, 9999),
            'name_ru' => 'Блюдо '.fake()->unique()->numberBetween(1, 9999),
            'price' => fake()->numberBetween(10, 300) * 100,
            'station' => 'kitchen',
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function bar(): static
    {
        return $this->state(fn () => ['station' => 'bar']);
    }
}
