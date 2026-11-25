<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table' => $this->whenLoaded('table', fn () => $this->table ? [
                'id' => $this->table->id,
                'number' => $this->table->number,
                'seats' => $this->table->seats,
            ] : null),
            'waiter' => $this->whenLoaded('waiter', fn () => $this->waiter ? [
                'id' => $this->waiter->id,
                'name' => $this->waiter->name,
            ] : null),
            'waiter_id' => $this->waiter_id,
            // Гость счёта (доставка, бронь или уже назван кассиру) — телефон и бонусы на кассе.
            'guest' => $this->whenLoaded('guest', fn () => $this->guest ? [
                'name' => $this->guest->name,
                'phone' => $this->guest->phone,
                'bonus_balance' => $this->guest->bonus_balance,
            ] : null),
            'source' => $this->source->value,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'guest_comment' => $this->guest_comment,
            'discount_percent' => $this->discount_percent,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'opened_at' => $this->opened_at->toIso8601String(),
            'bill_requested_at' => $this->bill_requested_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'delivery' => $this->whenLoaded('delivery', fn () => $this->delivery ? [
                'status' => $this->delivery->status->value,
                'name' => $this->delivery->customer_name,
                'phone' => $this->delivery->phone,
                'address' => $this->delivery->address,
                'planned_method' => $this->delivery->planned_method->value,
            ] : null),
            'payment' => $this->whenLoaded('activePayment', fn () => $this->activePayment
                ? new PaymentResource($this->activePayment) : null),
        ];
    }
}
