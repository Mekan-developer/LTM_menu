<?php

use App\Enums\SystemRole;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = storage_path('framework/testing/backups-'.uniqid());
    config(['restaurant.backup.path' => $this->dir, 'restaurant.backup.keep' => 14]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

/** pg_dump подменяется: «пишет» файл, указанный в --file, как настоящий. */
function fakePgDump(int $exitCode = 0): void
{
    Process::fake(function (PendingProcess $process) use ($exitCode) {
        $args = $process->command;
        if ($exitCode === 0) {
            File::put($args[array_search('--file', $args, true) + 1], 'PGDMP-fake');
        }

        return Process::result(errorOutput: $exitCode ? 'connection refused' : '', exitCode: $exitCode);
    });
}

it('делает копию PostgreSQL через pg_dump с паролем в окружении, а не в аргументах', function () {
    config(['restaurant.backup.connection' => 'pgsql', 'database.connections.pgsql.password' => 's3cret']);
    fakePgDump();

    $this->artisan('crm:backup')->assertSuccessful();

    $files = app(BackupService::class)->files();
    expect($files)->toHaveCount(1)
        ->and($files[0]['name'])->toEndWith('.dump');

    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'pg_dump'
        && in_array('--format=custom', $p->command, true)
        && ($p->environment['PGPASSWORD'] ?? null) === 's3cret'
        && ! in_array('s3cret', $p->command, true));
});

it('при ошибке pg_dump команда падает и не оставляет файла', function () {
    config(['restaurant.backup.connection' => 'pgsql']);
    fakePgDump(exitCode: 1);

    $this->artisan('crm:backup')->assertFailed();

    expect(File::glob($this->dir.'/*'))->toBe([]);
});

it('хранит только 14 последних копий', function () {
    File::ensureDirectoryExists($this->dir);
    foreach (range(1, 16) as $d) {
        File::put(sprintf('%s/restoran_2026-11-%02d_030000.dump', $this->dir, $d), 'x');
    }
    File::put($this->dir.'/other-file.txt', 'не трогать');

    $removed = app(BackupService::class)->prune();
    $names = array_column(app(BackupService::class)->files(), 'name');

    expect($removed)->toBe(2)
        ->and($names)->toHaveCount(14)
        ->and($names[0])->toBe('restoran_2026-11-16_030000.dump')
        ->and($names)->not->toContain('restoran_2026-11-01_030000.dump')
        ->and(File::exists($this->dir.'/other-file.txt'))->toBeTrue();
});

it('копирует файл SQLite в сжатом виде', function () {
    $db = storage_path('framework/testing/db-'.uniqid().'.sqlite');
    File::put($db, 'SQLite format 3 fake');
    config([
        'database.connections.backup_sqlite' => ['driver' => 'sqlite', 'database' => $db],
        'restaurant.backup.connection' => 'backup_sqlite',
    ]);

    $path = app(BackupService::class)->run();

    expect($path)->toEndWith('.sqlite.gz')
        ->and(gzdecode(File::get($path)))->toBe('SQLite format 3 fake');

    File::delete($db);
});

it('копия запланирована каждый день в 03:00', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'crm:backup'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *');
});

it('настройки показывают, что копии нет или она устарела', function () {
    $this->actingAs(staff(SystemRole::Owner))
        ->get(route('settings.edit'))
        ->assertInertia(fn ($page) => $page->where('backup.stale', true)->where('backup.count', 0));

    File::ensureDirectoryExists($this->dir);
    File::put($this->dir.'/restoran_'.now()->format('Y-m-d_His').'.dump', 'x');

    $this->get(route('settings.edit'))
        ->assertInertia(fn ($page) => $page->where('backup.stale', false)->where('backup.count', 1));
});
