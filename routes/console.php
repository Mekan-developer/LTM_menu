<?php

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
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

/*
| Резервная копия базы (ТЗ 3.1): php artisan crm:backup
| Каждый день в BACKUP_AT (по умолчанию 03:00 по Ашхабаду), хранятся BACKUP_KEEP (14) последних.
| Расписание выполняет сервис scheduler (php artisan schedule:work).
*/
Artisan::command('crm:backup {--list : Показать копии и не создавать новую}', function (BackupService $backups) {
    if ($this->option('list')) {
        $this->table(['Файл', 'Размер, МБ', 'Создан'], array_map(fn ($f) => [
            $f['name'], number_format($f['size'] / 1048576, 2), $f['created_at']->format('d.m.Y H:i'),
        ], $backups->files()));

        return 0;
    }

    try {
        $path = $backups->run();
    } catch (Throwable $e) {
        Log::error('Резервная копия не создана: '.$e->getMessage());
        $this->error('Резервная копия не создана: '.$e->getMessage());

        return 1;
    }

    $this->info('Резервная копия: '.$path);

    return 0;
})->purpose('Резервная копия базы данных');

Schedule::command('crm:backup')
    ->dailyAt((string) config('restaurant.backup.at'))
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('Плановая резервная копия завершилась с ошибкой'));
