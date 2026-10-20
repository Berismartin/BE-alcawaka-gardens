<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff start a Pesapal payment at the desk or POS. */
class OnlinePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount_ugx' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['required', Rule::in(['mtn_momo', 'airtel_money', 'card'])],
        ];
    }
}
