<?php

namespace App\Http\Requests\Menu;

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
        if ($this->has('price')) {
            $this->merge(['price' => str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $this->input('price'))]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name_tm' => ['required', 'string', 'max:160'],
            'name_ru' => ['required', 'string', 'max:160'],
            'description_tm' => ['nullable', 'string', 'max:1000'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'weight' => ['nullable', 'string', 'max:40'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'station' => ['required', Rule::enum(Station::class)],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['boolean'],
        ];
    }

    public function dishData(): array
    {
        return $this->safe()->except(['photo', 'remove_photo']);
    }
}
