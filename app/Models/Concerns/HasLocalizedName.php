<?php

namespace App\Models\Concerns;

trait HasLocalizedName
{
    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? $this->name_ru : $this->name_tm;
    }
}
