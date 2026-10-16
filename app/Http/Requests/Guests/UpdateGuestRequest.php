<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
            'nationality' => ['sometimes', 'nullable', 'string', 'max:60'],
            'id_number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
