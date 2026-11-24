<?php

namespace App\Http\Resources;

use App\Enums\OrderItemStatus;
use App\Models\Delivery;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ доставки / самовывоза для экранов «Доставка» и курьера.
 *
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $payment = $order->relationLoaded('activePayment') ? $order->activePayment : null;

        return [
            'id' => $this->id,
            'order_id' => $order->id,
            'type' => $order->channel->value,
            'status' => $this->status->value,
            'name' => $this->customer_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'comment' => $order->guest_comment,
            'source' => $order->source->value,
            'planned_method' => $this->planned_method->value,
            'courier' => $this->courier_id ? ['id' => $this->courier_id, 'name' => $this->courier?->name] : null,
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'total' => $order->total,
            'items' => $order->items
                ->filter(fn (OrderItem $i) => $i->status !== OrderItemStatus::Removed)
                ->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name_tm' => $i->name_tm,
                    'name_ru' => $i->name_ru,
                    'qty' => $i->quantity,
                    'price' => $i->price,
                    'status' => $i->status->value,
                    'comment' => $i->comment,
                ])->values()->all(),
            'payment' => $payment ? [
                'method' => $payment->method->value,
                'cash' => $payment->cash_amount,
                'card' => $payment->card_amount,
            ] : null,
            'cash_received' => $this->cash_received_at !== null,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'cooking_at' => $this->cooking_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'picked_at' => $this->picked_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'status_url' => route('guest.order', $order->public_token),
        ];
    }
}
