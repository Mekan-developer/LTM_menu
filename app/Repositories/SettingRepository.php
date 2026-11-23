<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return Setting::query()->pluck('value', 'key')->all();
    }

    public function put(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
