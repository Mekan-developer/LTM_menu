<?php

namespace App\Http\Resources;

use App\Models\Dish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Dish */
class DishResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name_tm' => $this->name_tm,
            'name_ru' => $this->name_ru,
            'description_tm' => $this->description_tm,
            'description_ru' => $this->description_ru,
            'weight' => $this->weight,
            'price' => $this->price,
            'station' => $this->station->value,
            'photo_url' => $this->photoUrl(),
            'is_active' => $this->is_active,
            'stopped' => $this->relationLoaded('stopEntry') && $this->stopEntry !== null
                && ($this->stopEntry->mode->value === 'manual' || $this->stopEntry->until?->isFuture()),
        ];
    }
}
