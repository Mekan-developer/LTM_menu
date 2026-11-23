<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // право проверяет middleware permission:menu_prices
    }

    public function rules(): array
    {
        return [
            'name_tm' => ['required', 'string', 'max:120'],
            'name_ru' => ['required', 'string', 'max:120'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
