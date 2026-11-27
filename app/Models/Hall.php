<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Database\Factories\HallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Зал со схемой: полотно width × height px, столы и объекты (ТЗ 2.2).
 * Зал с удалёнными столами в истории удаляется мягко.
 */
class Hall extends Model
{
    /** @use HasFactory<HallFactory> */
    use HasFactory, HasLocalizedName, SoftDeletes;

    protected $fillable = ['name_tm', 'name_ru', 'sort', 'width', 'height', 'is_active'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'sort' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    /** @return HasMany<HallElement, $this> */
    public function elements(): HasMany
    {
        return $this->hasMany(HallElement::class);
    }
}
