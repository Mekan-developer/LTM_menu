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

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cash_amount' => ['nullable', 'required_if:method,mixed', 'string', 'max:20'],
        ];
    }
}
