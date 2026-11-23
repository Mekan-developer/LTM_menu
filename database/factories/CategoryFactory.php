<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_tm' => 'Kategoriýa '.fake()->unique()->numberBetween(1, 9999),
            'name_ru' => 'Категория '.fake()->unique()->numberBetween(1, 9999),
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
