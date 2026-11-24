<?php

namespace App\Http\Requests\Deliveries;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Вручение: фактический способ оплаты, суммы при частичной оплате, чаевые курьеру (ТЗ 2.6). */
class CompleteDeliveryRequest extends FormRequest
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
            'cash_amount' => ['nullable', 'required_if:method,mixed', 'string', 'max:20'],
            'tip_amount' => ['nullable', 'string', 'max:20'],
            'tip_method' => ['nullable', 'required_with:tip_amount', Rule::in(['cash', 'card'])],
        ];
    }

    /** @return array{method:string, cash_amount:string|null, tip_amount:string|null, tip_method:string|null} */
    public function payData(): array
    {
        return [
            'method' => $this->string('method')->value(),
            'cash_amount' => $this->filled('cash_amount') ? $this->string('cash_amount')->value() : null,
            'tip_amount' => $this->filled('tip_amount') ? $this->string('tip_amount')->value() : null,
            'tip_method' => $this->filled('tip_method') ? $this->string('tip_method')->value() : null,
        ];
    }
}
