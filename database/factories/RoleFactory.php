<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => null,
            'name_tm' => 'Rol '.fake()->unique()->numberBetween(1, 99999),
            'name_ru' => 'Роль '.fake()->unique()->numberBetween(1, 99999),
            'is_system' => false,
        ];
    }
}
