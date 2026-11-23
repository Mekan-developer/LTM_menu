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
        ];
    }

    /** @return array{method: string, discount_percent: int|null, cash_amount: string|null} */
    public function payData(): array
    {
        return [
            'method' => $this->string('method')->value(),
            'discount_percent' => $this->filled('discount_percent') ? $this->integer('discount_percent') : null,
            'cash_amount' => $this->filled('cash_amount') ? $this->string('cash_amount')->value() : null,
        ];
    }
}
