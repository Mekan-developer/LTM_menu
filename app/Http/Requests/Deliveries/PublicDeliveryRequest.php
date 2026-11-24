<?php

namespace App\Http\Requests\Deliveries;

/** Заказ гостя на странице /delivery: цены из меню гостя сверяются на сервере (ТЗ 2.3). */
class PublicDeliveryRequest extends DeliveryRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['delivery_fee']); // стоимость доставки гость не задаёт

        return [
            ...$rules,
            'comment' => ['nullable', 'string', 'max:250'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }
}
