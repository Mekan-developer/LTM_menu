<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class GuestOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.dish_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
            'items.*.price' => ['required', 'integer', 'min:0'], // цена из меню гостя, в тенге
            'comment' => ['nullable', 'string', 'max:250'],
        ];
    }
}
