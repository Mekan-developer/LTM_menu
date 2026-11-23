<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dish_id' => $this->dish_id,
            'name_tm' => $this->name_tm,
            'name_ru' => $this->name_ru,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'total' => $this->lineTotal(),
            'station' => $this->station->value,
            'status' => $this->status->value,
            'comment' => $this->comment,
            'batch' => $this->batch,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'removed_reason' => $this->removed_reason,
        ];
    }
}
