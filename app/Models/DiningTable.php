<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableShape;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Стол: номер, места, QR-код и место на схеме зала (ТЗ 2.2). Размер — без учёта поворота.
 * Стол с историей заказов удаляется мягко: его нет в списках и на схеме, QR не работает.
 */
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'hall_id', 'seats', 'qr_token', 'is_active', 'sort',
        'shape', 'pos_x', 'pos_y', 'width', 'height', 'rotation',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'seats' => 'integer', 'sort' => 'integer',
            'shape' => TableShape::class,
            'pos_x' => 'integer', 'pos_y' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'rotation' => 'integer',
        ];
    }

    /** @return BelongsTo<Hall, $this> */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasOne<Order, $this> */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->whereIn('status', OrderStatus::activeValues())
            ->latestOfMany();
    }
}
