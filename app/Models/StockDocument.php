<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\WriteOffReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Складской документ: приход, ручное списание или инвентаризация. */
class StockDocument extends Model
{
    protected $fillable = ['branch_id', 'type', 'reason', 'note', 'total', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reason' => WriteOffReason::class,
            'total' => 'integer',
        ];
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
