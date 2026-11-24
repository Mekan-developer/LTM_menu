<?php

namespace App\Http\Requests\Staff;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Стоимость доставки вводится в TMT («15» или «15,50»), хранится в тенге. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('delivery_fee')) {
            $this->merge(['delivery_fee' => Money::toMinor($this->string('delivery_fee')->value())]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'restaurant_name' => ['required', 'string', 'max:120'],
            'discount_limit_k' => ['required', 'integer', 'min:0', 'max:100'],
            'qr_confirmation' => ['required', 'boolean'],
            'kitchen_warn_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'kitchen_late_minutes' => ['required', 'integer', 'gt:kitchen_warn_minutes', 'max:240'],
            'shared_idle_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
            'reviews_enabled' => ['required', 'boolean'],
            'booking_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'booking_reserve_before' => ['required', 'integer', 'min:0', 'max:360'],
            'online_booking' => ['required', 'boolean'],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:1000000'],
            'online_delivery' => ['required', 'boolean'],
        ];
    }
}
