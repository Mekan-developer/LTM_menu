<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Резервные копии базы (ТЗ 3.1): раз в сутки, хранятся 14 последних.
 * PostgreSQL — pg_dump в сжатом формате custom (восстановление: pg_restore),
 * SQLite (локальная разработка) — копия файла, сжатая gzip.
 * Файл пишется во временный .part и переименовывается только после успеха.
 */
class BackupService
{
    public const PREFIX = 'restoran_';

    /** Сделать копию и удалить лишние. Возвращает путь к новому файлу. */
    public function run(): string
    {
        $dir = $this->dir();
        File::ensureDirectoryExists($dir, 0750);

        $connection = $this->connection();
        $config = config("database.connections.$connection");

        if (! is_array($config)) {
            throw new RuntimeException("Unknown database connection [$connection].");
        }

        $stamp = now()->format('Y-m-d_His');
        $path = match ($config['driver']) {
            'pgsql' => $this->dumpPostgres($config, "$dir/".self::PREFIX."$stamp.dump"),
            'sqlite' => $this->copySqlite($config, "$dir/".self::PREFIX."$stamp.sqlite.gz"),
            default => throw new RuntimeException("Backup of driver [{$config['driver']}] is not supported."),
        };

        $this->prune();

        return $path;
    }

    /** Оставить N последних копий. Возвращает число удалённых. */
    public function prune(): int
    {
        $keep = max(1, (int) config('restaurant.backup.keep'));
        $old = array_slice($this->files(), $keep);

        foreach ($old as $file) {
            File::delete($file['path']);
        }

        return count($old);
    }

    /**
     * Копии, новые первыми.
     *
     * @return array<int, array{name: string, path: string, size: int, created_at: Carbon}>
     */
    public function files(): array
    {
        if (! File::isDirectory($this->dir())) {
            return [];
        }

        $files = collect(File::files($this->dir()))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), self::PREFIX) && ! str_ends_with($f->getFilename(), '.part'))
            ->map(fn ($f) => [
                'name' => $f->getFilename(),
                'path' => $f->getPathname(),
                'size' => (int) $f->getSize(),
                'created_at' => Carbon::createFromTimestamp($f->getMTime(), (string) config('app.timezone')),
            ])
            // Имя содержит дату и время — по нему сортировка надёжнее, чем по mtime после копирования.
            ->sortByDesc('name')
            ->values()
            ->all();

        return $files;
    }

    /**
     * Для страницы настроек: когда была последняя копия и не устарела ли она.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $files = $this->files();
        $last = $files[0] ?? null;
        $staleAfter = now()->subHours((int) config('restaurant.backup.stale_hours'));

        return [
            'count' => count($files),
            'keep' => (int) config('restaurant.backup.keep'),
            'last_at' => $last ? $last['created_at']->toIso8601String() : null,
            'last_size' => $last['size'] ?? null,
            'stale' => $last === null || $last['created_at']->lessThan($staleAfter),
            'path' => $this->dir(),
            'at' => config('restaurant.backup.at'),
        ];
    }

    private function dir(): string
    {
        return rtrim((string) config('restaurant.backup.path'), '/');
    }

    private function connection(): string
    {
        return (string) (config('restaurant.backup.connection') ?: config('database.default'));
    }

    /** @param array<string, mixed> $db */
    private function dumpPostgres(array $db, string $path): string
    {
        $part = "$path.part";

        $result = Process::timeout(900)
            ->env(['PGPASSWORD' => (string) ($db['password'] ?? '')])
            ->run([
                'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
                '--host', (string) $db['host'], '--port', (string) $db['port'],
                '--username', (string) $db['username'],
                '--file', $part,
                (string) $db['database'],
            ]);

        if (! $result->successful() || ! File::exists($part) || File::size($part) === 0) {
            File::delete($part);

            throw new RuntimeException('pg_dump failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        File::move($part, $path);

        return $path;
    }

    /** @param array<string, mixed> $db */
    private function copySqlite(array $db, string $path): string
    {
        $source = (string) $db['database'];

        if ($source === ':memory:' || ! File::exists($source)) {
            throw new RuntimeException("SQLite database file [$source] not found.");
        }

        $part = "$path.part";
        $gz = gzopen($part, 'wb9');

        if ($gz === false) {
            throw new RuntimeException("Cannot write [$part].");
        }

        gzwrite($gz, (string) File::get($source));
        gzclose($gz);
        File::move($part, $path);

        return $path;
    }
}
