<?php

namespace App\Http\Requests\Costing;

use App\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // «1 234,50» → «1234.50»
        if (filled($this->input('price'))) {
            $this->merge(['price' => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $this->input('price'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_tm' => ['required', 'string', 'max:120'],
            'name_ru' => ['required', 'string', 'max:120'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            // Цена закупки за единицу (кг, л или шт), TMT.
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }
}
