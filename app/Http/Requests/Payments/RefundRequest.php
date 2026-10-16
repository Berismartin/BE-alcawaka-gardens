<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount_ugx' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
