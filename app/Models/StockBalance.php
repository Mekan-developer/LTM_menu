<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Остаток продукта в филиале и минимум для «Нужно закупить». */
class StockBalance extends Model
{
    protected $fillable = ['branch_id', 'product_id', 'quantity', 'min_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'min_quantity' => 'float'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
