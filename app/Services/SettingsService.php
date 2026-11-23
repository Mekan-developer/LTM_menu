<?php

namespace App\Services;

use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'crm.settings';

    private ?array $resolved = null;

    public function __construct(private readonly SettingRepository $settings) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $defaults = config('restaurant.defaults');

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => $this->settings->all());
        } catch (Throwable) {
            $stored = []; // до первой миграции таблицы ещё нет
        }

        return $this->resolved = array_merge($defaults, array_intersect_key($stored, $defaults));
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function update(array $values): void
    {
        foreach (array_intersect_key($values, config('restaurant.defaults')) as $key => $value) {
            $this->settings->put($key, $value);
        }

        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
