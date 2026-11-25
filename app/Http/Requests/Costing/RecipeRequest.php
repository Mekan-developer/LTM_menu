<?php

namespace App\Http\Requests\Costing;

use Illuminate\Foundation\Http\FormRequest;

/** Состав блюда на порцию; количество — в единице продукта (кг, л, шт). */
class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:60'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:9999'],
        ];
    }

    /** @return list<array{product_id: int, quantity: float|int|string}> */
    public function lines(): array
    {
        return array_values($this->validated('lines') ?? []);
    }
}
