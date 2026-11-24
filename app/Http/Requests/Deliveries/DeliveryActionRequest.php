<?php

namespace App\Http\Requests\Deliveries;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Действие над заказом доставки: accept | kitchen | courier | fee | pick_up | cancel.
 * Причина нужна для отмены, курьер — для назначения, сумма — для стоимости доставки.
 */
class DeliveryActionRequest extends FormRequest
{
    public const ACTIONS = ['accept', 'kitchen', 'courier', 'fee', 'pick_up', 'cancel'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('action') === 'fee' && $this->filled('delivery_fee')) {
            $this->merge(['delivery_fee' => Money::toMinor($this->string('delivery_fee')->value())]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'reason' => ['nullable', 'required_if:action,cancel', 'string', 'max:250'],
            'courier_id' => ['nullable', 'integer', 'exists:users,id'],
            'delivery_fee' => ['nullable', 'required_if:action,fee', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
