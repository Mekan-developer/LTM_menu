<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

/** Бронь гостя на странице (ТЗ 2.2). Стол назначает администратор. */
class PublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'comment' => ['nullable', 'string', 'max:500'],
            'items' => ['array', 'max:30'],
            'items.*.dish_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }
}
