<?php

namespace Database\Factories;

use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DiningTable> */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1, 999),
            'hall' => null,
            'seats' => 4,
            'qr_token' => Str::lower(Str::random(32)),
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
