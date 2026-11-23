<?php

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/*
| Пароль Владельца сбрасывает Исполнитель (ТЗ 1.3):
| php artisan crm:owner-password owner
*/
Artisan::command('crm:owner-password {login} {password?}', function (string $login, ?string $password = null) {
    $user = User::query()->where('login', $login)->first();

    if ($user === null || ! $user->isOwner()) {
        $this->error('Владелец с таким логином не найден.');

        return 1;
    }

    $password ??= Str::password(12, symbols: false);
    $user->forceFill([
        'password' => $password,
        'is_active' => true,
    ])->save();

    $this->info("Новый пароль владельца {$login}: {$password}");

    return 0;
})->purpose('Сбросить пароль Владельца');

Artisan::command('crm:roles', function () {
    $this->table(['slug', 'RU'], collect(SystemRole::cases())->map(fn ($r) => [$r->value, $r->nameRu()]));
})->purpose('Показать системные роли');
