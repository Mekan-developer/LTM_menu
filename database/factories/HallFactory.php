<?php

namespace Database\Factories;

use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hall> */
class HallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_tm' => 'Zal '.fake()->unique()->numberBetween(1, 999),
            'name_ru' => 'Зал',
            'sort' => 0,
            'width' => 1000,
            'height' => 640,
            'is_active' => true,
        ];
    }
}
