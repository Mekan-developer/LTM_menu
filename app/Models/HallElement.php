<?php

namespace App\Models;

use App\Enums\HallElementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Бар, вход, окно, стена или подпись на схеме. Не кликается в режиме «Работа». */
class HallElement extends Model
{
    protected $fillable = ['hall_id', 'type', 'label_tm', 'label_ru', 'pos_x', 'pos_y', 'width', 'height'];

    protected function casts(): array
    {
        return [
            'type' => HallElementType::class,
            'pos_x' => 'integer', 'pos_y' => 'integer', 'width' => 'integer', 'height' => 'integer',
        ];
    }

    /** @return BelongsTo<Hall, $this> */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
