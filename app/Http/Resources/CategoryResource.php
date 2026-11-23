<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_tm' => $this->name_tm,
            'name_ru' => $this->name_ru,
            'sort' => $this->sort,
            'is_active' => $this->is_active,
            'stopped' => $this->relationLoaded('stopEntry') && $this->stopEntry !== null
                && ($this->stopEntry->mode->value === 'manual' || $this->stopEntry->until?->isFuture()),
            'dishes' => DishResource::collection($this->whenLoaded('dishes')),
        ];
    }
}
