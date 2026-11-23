<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->route('user') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'login' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('users', 'login')->ignore($this->route('user'))],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::min(8)],
            'pin' => ['nullable', 'digits:4'],
            'clear_pin' => ['boolean'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
