<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class SetPinRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
            'current_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['pin.regex' => 'The PIN must be 4 to 6 digits.'];
    }
}
