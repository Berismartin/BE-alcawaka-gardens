<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/** Pesapal IPN (GET query or POST body). The values are only a hint: the status is always re-checked with Pesapal. */
class PesapalIpnRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'OrderTrackingId' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'OrderNotificationType' => ['nullable', 'string', 'max:30'],
            'OrderMerchantReference' => ['nullable', 'string', 'max:64'],
        ];
    }
}
