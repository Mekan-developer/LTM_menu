<?php

namespace App\Http\Resources;

use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiningTable */
class TableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'hall_id' => $this->hall_id,
            'hall' => $this->whenLoaded('hall', fn () => $this->hall?->localizedName()),
            'shape' => $this->shape->value,
            'seats' => $this->seats,
            'is_active' => $this->is_active,
            'qr_url' => route('guest.menu', $this->qr_token),
            'busy' => $this->relationLoaded('activeOrder') && $this->activeOrder !== null,
        ];
    }
}
