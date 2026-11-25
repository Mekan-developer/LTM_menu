<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Продукт для калькуляции и склада: название, единица (кг, л, шт), цена закупки за единицу (ТЗ 2.3). */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasLocalizedName;

    protected $fillable = ['name_tm', 'name_ru', 'unit', 'price'];

    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'price' => 'integer',
        ];
    }

    /** @return HasMany<DishIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(DishIngredient::class);
    }
}
