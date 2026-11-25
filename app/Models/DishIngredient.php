<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Строка состава блюда: сколько продукта уходит на одну порцию (в единице продукта). */
class DishIngredient extends Model
{
    protected $fillable = ['dish_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }

    /** @return BelongsTo<Dish, $this> */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Стоимость строки в тенге по текущей цене продукта. */
    public function cost(): int
    {
        return (int) round($this->quantity * ($this->product->price ?? 0));
    }
}
