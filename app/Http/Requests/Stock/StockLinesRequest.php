<?php

namespace App\Http\Requests\Stock;

use App\Enums\WriteOffReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Приход или ручное списание: строки «продукт — количество (— цена)».
 * Количество — в единице продукта (кг, л, шт); цена прихода — TMT за единицу.
 */
class StockLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (is_array($lines)) {
            $this->merge(['lines' => array_map(fn ($l) => is_array($l) ? [
                ...$l,
                'quantity' => self::number($l['quantity'] ?? null),
                'price' => self::number($l['price'] ?? null),
            ] : $l, $lines)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $receipt = $this->route()?->getName() === 'stock.receipts.store';

        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'lines.*.price' => $receipt ? ['required', 'numeric', 'min:0', 'max:999999'] : ['nullable'],
            'reason' => $receipt ? ['nullable'] : ['required', Rule::enum(WriteOffReason::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<array{product_id: int, quantity: float|int|string, price: string|int|float}> */
    public function lines(): array
    {
        return array_values($this->validated('lines'));
    }

    /** «1 234,5» → «1234.5» */
    private static function number(mixed $value): mixed
    {
        return is_string($value) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value) : $value;
    }
}
