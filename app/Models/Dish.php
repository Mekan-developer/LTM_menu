<?php

namespace App\Models;

use App\Enums\Station;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory, HasLocalizedName;

    protected $fillable = [
        'category_id', 'name_tm', 'name_ru', 'description_tm', 'description_ru',
        'weight', 'price', 'station', 'photo_path', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'station' => Station::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return MorphOne<StopListEntry, $this> */
    public function stopEntry(): MorphOne
    {
        return $this->morphOne(StopListEntry::class, 'stoppable');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        return ($locale ?? app()->getLocale()) === 'ru' ? $this->description_ru : $this->description_tm;
    }
}
