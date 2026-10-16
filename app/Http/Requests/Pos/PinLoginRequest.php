<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PinLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }
}
