<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('slug', SystemRole::Owner->value)->firstOrFail();
        $login = config('restaurant.owner.login');

        if (User::where('login', $login)->exists()) {
            return;
        }

        User::create([
            'name' => 'Владелец',
            'login' => $login,
            'password' => config('restaurant.owner.password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->command?->warn("Создан владелец «{$login}». Смените пароль после первого входа.");
    }
}
