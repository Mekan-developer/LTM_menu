<?php

namespace App\Http\Requests\Menu;

use App\Enums\DishKind;
use App\Enums\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // «1 234,50» → «1234.50»
        foreach (['price', 'sale_price'] as $key) {
            if (filled($this->input($key))) {
                $this->merge([$key => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $this->input($key))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name_tm' => ['required', 'string', 'max:160'],
            'name_ru' => ['required', 'string', 'max:160'],
            'description_tm' => ['nullable', 'string', 'max:1000'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'string', 'max:40'],
            'kind' => ['required', Rule::enum(DishKind::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            // Акция: цена ниже обычной; период необязателен (пусто — без ограничения).
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'sale_from' => ['nullable', 'date_format:Y-m-d'],
            'sale_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:sale_from'],
            'station' => ['required', Rule::enum(Station::class)],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function dishData(): array
    {
        return $this->safe()->except(['photo', 'remove_photo']);
    }
}
