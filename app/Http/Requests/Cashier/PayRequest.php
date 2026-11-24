<?php

namespace App\Http\Requests\Cashier;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cash_amount' => ['nullable', 'required_if:method,mixed', 'string', 'max:20'],
            'tip_amount' => ['nullable', 'string', 'max:20'],
            'tip_method' => ['nullable', 'required_with:tip_amount', Rule::in(['cash', 'card'])],
            'guest_phone' => ['nullable', 'string', 'max:32'],
            'guest_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array{method: string, discount_percent: int|null, cash_amount: string|null, tip_amount: string|null, tip_method: string|null, guest_phone: string|null, guest_name: string|null} */
    public function payData(): array
    {
        return [
            'method' => $this->string('method')->value(),
            'discount_percent' => $this->filled('discount_percent') ? $this->integer('discount_percent') : null,
            'cash_amount' => $this->filled('cash_amount') ? $this->string('cash_amount')->value() : null,
            'tip_amount' => $this->filled('tip_amount') ? $this->string('tip_amount')->value() : null,
            'tip_method' => $this->filled('tip_method') ? $this->string('tip_method')->value() : null,
            'guest_phone' => $this->filled('guest_phone') ? $this->string('guest_phone')->value() : null,
            'guest_name' => $this->filled('guest_name') ? $this->string('guest_name')->value() : null,
        ];
    }
}
