<?php

namespace App\Models;

use App\Enums\Station;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Позиция предзаказа: уходит на кухню, когда администратор отметит «гость пришёл». */
class BookingItem extends Model
{
    protected $fillable = ['booking_id', 'dish_id', 'name_tm', 'name_ru', 'price', 'quantity', 'station'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'quantity' => 'integer', 'station' => Station::class];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
