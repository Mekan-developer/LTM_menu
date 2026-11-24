<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guest> */
class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => '+9936'.fake()->unique()->numerify('#######'),
            'source' => 'staff',
        ];
    }
}
