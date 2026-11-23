<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'login' => fake()->unique()->userName(),
            'password' => static::$password ??= 'password',
            'pin' => null,
            'role_id' => Role::factory(),
            'is_active' => true,
        ];
    }

    /** Сотрудник с системной ролью (роли должен создать RoleSeeder). */
    public function role(SystemRole $role): static
    {
        return $this->state(fn () => ['role_id' => Role::where('slug', $role->value)->value('id')]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn () => ['pin' => $pin]);
    }
}
