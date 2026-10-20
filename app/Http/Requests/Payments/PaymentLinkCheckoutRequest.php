<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Public: the guest pays an invoice link. */
class PaymentLinkCheckoutRequest extends FormRequest
{
    public function rules(): array
    {
        return ['method' => ['required', Rule::in(['mtn_momo', 'airtel_money', 'card'])]];
    }
}
