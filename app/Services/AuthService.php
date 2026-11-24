<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Вход по логину и паролю и по 4-значному PIN на общем планшете.
 * После 5 неверных попыток — блокировка на 15 минут (ТЗ 1.3, 3.5).
 */
class AuthService
{
    public function attemptPassword(string $login, string $password, string $ip, bool $remember = false): User
    {
        $key = 'login:'.Str::lower($login).'|'.$ip;
        $max = (int) config('restaurant.auth.max_attempts');
        $lockSeconds = (int) config('restaurant.auth.lock_minutes') * 60;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                'login' => __('crm.auth.locked', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        $user = User::query()->where('login', $login)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, $lockSeconds);

            throw ValidationException::withMessages(['login' => __('crm.auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => __('crm.auth.blocked')]);
        }

        RateLimiter::clear($key);
        Auth::login($user, $remember);

        return $user;
    }

    public function attemptPin(User $user, string $pin): void
    {
        $max = (int) config('restaurant.auth.max_attempts');
        $lockMinutes = (int) config('restaurant.auth.lock_minutes');

        if (! $user->is_active || $user->pin === null) {
            throw ValidationException::withMessages(['pin' => __('crm.auth.failed')]);
        }

        if ($user->pin_locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'pin' => __('crm.auth.locked', ['minutes' => (int) ceil(now()->diffInSeconds($user->pin_locked_until) / 60)]),
            ]);
        }

        if (! Hash::check($pin, $user->pin)) {
            $attempts = $user->pin_failed_attempts + 1;

            if ($attempts >= $max) {
                $user->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => now()->addMinutes($lockMinutes)])->save();

                throw ValidationException::withMessages(['pin' => __('crm.auth.locked', ['minutes' => $lockMinutes])]);
            }

            $user->forceFill(['pin_failed_attempts' => $attempts])->save();

            throw ValidationException::withMessages(['pin' => __('crm.auth.pin_left', ['left' => $max - $attempts])]);
        }

        $user->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();
        Auth::login($user);
    }

    /** Куда отправить сотрудника после входа — на его рабочий экран. */
    public function homeRouteFor(User $user): string
    {
        return match (true) {
            $user->hasPermission(Permission::Reports) => 'dashboard',
            $user->hasPermission(Permission::AreaCashier) => 'cashier.index',
            $user->hasPermission(Permission::AreaKitchen) => 'kitchen.index',
            $user->hasPermission(Permission::AreaWaiter) => 'waiter.index',
            $user->hasPermission(Permission::AreaCourier) => 'courier.index',
            $user->hasPermission(Permission::Delivery) => 'deliveries.index',
            $user->hasPermission(Permission::Menu) => 'menu.index',
            $user->hasPermission(Permission::StopList) => 'stop-list.index',
            default => 'no-access',
        };
    }
}
