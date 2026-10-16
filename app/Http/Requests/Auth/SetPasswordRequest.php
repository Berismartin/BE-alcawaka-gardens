<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Accept invitation and reset password. */
class SetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'max:200'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }
}
