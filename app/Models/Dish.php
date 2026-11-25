<?php

namespace App\Models;

use App\Enums\DishKind;
use App\Enums\Station;
use App\Models\Concerns\HasLocalizedName;
use Carbon\CarbonInterface;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory, HasLocalizedName;

    protected $fillable = [
        'category_id', 'kind', 'name_tm', 'name_ru', 'description_tm', 'description_ru',
        'weight', 'price', 'sale_price', 'sale_from', 'sale_until', 'station', 'photo_path', 'is_active', 'sort',
    ];

    protected $attributes = [
        'kind' => 'made',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DishKind::class,
            'price' => 'integer',
            'sale_price' => 'integer',
            'sale_from' => 'date',
            'sale_until' => 'date',
            'station' => Station::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Действует ли акционная цена: задана, ниже обычной и сегодня внутри периода (границы включительно).
     * Период без начала или конца — открытый с этой стороны.
     */
    public function onSale(?CarbonInterface $at = null): bool
    {
        if ($this->sale_price === null || $this->sale_price >= $this->price) {
            return false;
        }

        $day = ($at ?? now())->toDateString();

        return ($this->sale_from === null || $this->sale_from->toDateString() <= $day)
            && ($this->sale_until === null || $this->sale_until->toDateString() >= $day);
    }

    /** Цена, по которой блюдо продаётся сейчас, — её берут в заказ и показывают гостю. */
    public function currentPrice(?CarbonInterface $at = null): int
    {
        return $this->onSale($at) ? (int) $this->sale_price : $this->price;
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Состав на одну порцию (калькуляция, ТЗ 2.3). У готовой продукции — один товар. */
    /** @return HasMany<DishIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(DishIngredient::class)->orderBy('id');
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
