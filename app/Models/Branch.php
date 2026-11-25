<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

/**
 * Филиал. Пока одна запись «Основной филиал» без интерфейса (ТЗ разд. 4 отложен):
 * всё, что по ТЗ «своё в каждом филиале», уже хранится с branch_id.
 */
class Branch extends Model
{
    use HasLocalizedName;

    protected $fillable = ['name_tm', 'name_ru', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** Филиал, в котором сейчас работает система. Когда появятся филиалы — будет выбранный пользователем. */
    public static function currentId(): int
    {
        return once(fn () => (int) (self::query()->where('is_default', true)->value('id')
            ?? self::query()->orderBy('id')->value('id')
            ?? self::query()->create(['name_tm' => 'Esasy şahamça', 'name_ru' => 'Основной филиал', 'is_default' => true])->id));
    }
}
