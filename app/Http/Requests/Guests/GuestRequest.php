<?php

namespace App\Http\Requests\Guests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Карточка гостя (ТЗ 2.1): телефон +993 XX XXXXXX, один номер — один гость. */
class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->string('phone')->value()) ?? $this->input('phone')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+993\d{8}$/', Rule::unique('guests', 'phone')->ignore($this->route('guest'))],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => __('crm.guests.phone_invalid'),
            'phone.unique' => __('crm.guests.phone_taken'),
        ];
    }
}
