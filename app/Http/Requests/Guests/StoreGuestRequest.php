<?php

namespace App\Http\Requests\Guests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:160'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
