<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DiningTable> */
class DiningTableFactory extends Factory
{
    /** Счётчик для раскладки сеткой — столы фабрики не пересекаются. */
    private static int $slot = 0;

    public function definition(): array
    {
        $slot = self::$slot++;

        return [
            'number' => (string) fake()->unique()->numberBetween(1, 9999),
            // Все столы фабрики — в одном зале, если он уже есть.
            'hall_id' => fn () => Hall::query()->value('id') ?? Hall::factory()->create(['height' => 2000])->id,
            'seats' => 4,
            'qr_token' => Str::lower(Str::random(32)),
            'is_active' => true,
            'sort' => 0,
            'shape' => 'square',
            'pos_x' => 40 + ($slot % 7) * 120,
            'pos_y' => 40 + intdiv($slot, 7) % 15 * 120,
            'width' => 80,
            'height' => 80,
            'rotation' => 0,
        ];
    }
}
