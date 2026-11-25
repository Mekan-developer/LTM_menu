<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'method' => $this->method->value,
            'cash_amount' => $this->cash_amount,
            'card_amount' => $this->card_amount,
            'bonus_amount' => $this->bonus_amount,
            'bonus_accrued' => $this->bonus_accrued,
            'total' => $this->total,
            'created_at' => $this->created_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'table' => $this->whenLoaded('order', fn () => $this->order?->relationLoaded('table') ? $this->order->table?->number : null),
            'cashier' => $this->whenLoaded('cashier', fn () => $this->cashier?->name),
            // Доставка и самовывоз: вместо стола — канал и номер заказа (ТЗ 2.6).
            'channel' => $this->whenLoaded('order', fn () => $this->order?->channel?->value),
        ];
    }
}
