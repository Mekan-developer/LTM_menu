<?php

namespace App\Http\Requests\Hall;

use App\Enums\HallElementType;
use App\Enums\TableShape;
use App\Services\HallService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Схема зала целиком: столы и объекты. Геометрию (границы, пересечения) проверяет HallService. */
class HallLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $min = HallService::MIN_SIZE;

        return [
            // Версия схемы, с которой начали правку (HallService::saveLayout).
            'revision' => ['required', 'string'],

            'tables' => ['present', 'array', 'max:300'],
            'tables.*.id' => ['nullable', 'integer'],
            'tables.*.number' => ['required', 'string', 'max:16', 'distinct'],
            'tables.*.seats' => ['required', 'integer', 'min:1', 'max:50'],
            'tables.*.shape' => ['required', Rule::enum(TableShape::class)],
            'tables.*.pos_x' => ['required', 'integer', 'between:-10000,10000'],
            'tables.*.pos_y' => ['required', 'integer', 'between:-10000,10000'],
            'tables.*.width' => ['required', 'integer', "min:$min", 'max:1000'],
            'tables.*.height' => ['required', 'integer', "min:$min", 'max:1000'],
            'tables.*.rotation' => ['required', 'integer', Rule::in([0, 90])],

            'elements' => ['present', 'array', 'max:200'],
            'elements.*.id' => ['nullable', 'integer'],
            'elements.*.type' => ['required', Rule::enum(HallElementType::class)],
            'elements.*.label_tm' => ['nullable', 'string', 'max:64'],
            'elements.*.label_ru' => ['nullable', 'string', 'max:64'],
            'elements.*.pos_x' => ['required', 'integer', 'between:-10000,10000'],
            'elements.*.pos_y' => ['required', 'integer', 'between:-10000,10000'],
            'elements.*.width' => ['required', 'integer', 'min:20', 'max:4000'],
            'elements.*.height' => ['required', 'integer', 'min:20', 'max:4000'],
        ];
    }
}
