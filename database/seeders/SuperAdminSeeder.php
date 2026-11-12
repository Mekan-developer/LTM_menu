<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Create (or update) the platform super admin.
     *
     * Safe to run repeatedly: the account is matched by email. Credentials
     * come from config('auth.super_admin') (SUPER_ADMIN_* env variables).
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $config */
        $config = config('auth.super_admin');

        $user = User::query()->firstOrNew(['email' => $config['email']]);

        $user->forceFill([
            'name' => $config['name'],
            'password' => $config['password'],
            'role' => UserRole::SuperAdmin,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
