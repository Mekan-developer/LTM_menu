<?php

namespace App\Http\Requests\Bookings;

use Illuminate\Foundation\Http\FormRequest;

/** Бронь от администратора по звонку (ТЗ 2.2): дата, время, гости, имя, телефон, стол, предзаказ. */
class BookingRequest extends FormRequest
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
            'guests_count' => ['required', 'integer', 'min:1', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'comment' => ['nullable', 'string', 'max:500'],
            'dining_table_id' => ['nullable', 'integer', 'exists:dining_tables,id'],
            'confirm' => ['boolean'],
            'items' => ['array', 'max:50'],
            'items.*.dish_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }
}
