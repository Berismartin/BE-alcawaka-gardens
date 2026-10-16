<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class RegisterTerminalRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80']];
    }
}
