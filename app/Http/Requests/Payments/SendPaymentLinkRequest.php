<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SendPaymentLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:160'],
            'amount_ugx' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
