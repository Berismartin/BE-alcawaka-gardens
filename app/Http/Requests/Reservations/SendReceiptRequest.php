<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class SendReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => ['nullable', 'email', 'max:160']];
    }
}
