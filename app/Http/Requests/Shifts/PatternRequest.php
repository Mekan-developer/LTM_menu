<?php

namespace App\Http\Requests\Shifts;

use Illuminate\Foundation\Http\FormRequest;

/** Шаблон графика: N дней работы / M выходных по кругу (2/2, 5/2, 1/3…) на период до двух месяцев. */
class PatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now()->addDays(92)->toDateString()],
            'work' => ['required', 'integer', 'min:1', 'max:14'],
            'off' => ['required', 'integer', 'min:0', 'max:14'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'different:start'],
        ];
    }
}
