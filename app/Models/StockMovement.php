<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Движение по складу: количество со знаком, цена за единицу и сумма на момент движения. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id', 'product_id', 'type', 'quantity', 'price', 'amount', 'balance_after',
        'document_id', 'order_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'float',
            'price' => 'integer',
            'amount' => 'integer',
            'balance_after' => 'float',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
