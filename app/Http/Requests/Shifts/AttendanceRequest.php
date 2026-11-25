<?php

namespace App\Http\Requests\Shifts;

use Illuminate\Foundation\Http\FormRequest;

/** Исправление отметки или внесение забытой — всегда с причиной (ТЗ 2.12). */
class AttendanceRequest extends FormRequest
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
            'clock_in' => ['required', 'date_format:Y-m-d\TH:i'],
            'clock_out' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
