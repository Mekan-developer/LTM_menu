<?php

namespace App\Http\Requests\Hall;

use Illuminate\Foundation\Http\FormRequest;

class HallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_tm' => ['required', 'string', 'max:64'],
            'name_ru' => ['required', 'string', 'max:64'],
            'width' => ['required', 'integer', 'min:400', 'max:4000'],
            'height' => ['required', 'integer', 'min:300', 'max:4000'],
        ];
    }
}
