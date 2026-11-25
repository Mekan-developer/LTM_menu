<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

/** Инвентаризация: фактический остаток по каждому посчитанному продукту. */
class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $counts = $this->input('counts');

        if (is_array($counts)) {
            $this->merge(['counts' => array_map(fn ($c) => is_array($c) && is_string($c['actual'] ?? null)
                ? [...$c, 'actual' => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $c['actual'])]
                : $c, $counts)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counts' => ['required', 'array', 'min:1', 'max:2000'],
            'counts.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'counts.*.actual' => ['required', 'numeric', 'min:0', 'max:99999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<array{product_id: int, actual: float|int|string}> */
    public function counts(): array
    {
        return array_values($this->validated('counts'));
    }
}
