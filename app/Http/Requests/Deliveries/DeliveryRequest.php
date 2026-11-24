<?php

namespace App\Http\Requests\Deliveries;

use App\Support\Money;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Заказ на доставку или самовывоз (ТЗ 2.6). Базовые правила — для заказа по звонку;
 * гостевая форма на странице (PublicDeliveryRequest) добавляет цены из меню гостя.
 */
class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->string('phone')->value()) ?? $this->input('phone')]);
        }

        // Стоимость доставки вводится в TMT («15» или «15,50») — храним в тенге.
        if ($this->filled('delivery_fee')) {
            $this->merge(['delivery_fee' => Money::toMinor($this->string('delivery_fee')->value())]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['delivery', 'pickup'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+993\d{8}$/'],
            'address' => ['nullable', 'required_if:type,delivery', 'string', 'max:500'],
            'method' => ['required', Rule::in(['cash', 'card'])],
            'comment' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.dish_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
            'items.*.comment' => ['nullable', 'string', 'max:250'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => __('crm.guests.phone_invalid'),
            'address.required_if' => __('crm.delivery.address_required'),
        ];
    }
}
