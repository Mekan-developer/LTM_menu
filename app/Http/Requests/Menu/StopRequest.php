<?php

namespace App\Http\Requests\Menu;

use App\Enums\StopMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['dish', 'category'])],
            'id' => ['required', 'integer'],
            'mode' => ['required', Rule::enum(StopMode::class)],
            'until' => ['nullable', 'required_if:mode,until_time', 'date', 'after:now'],
        ];
    }
}
