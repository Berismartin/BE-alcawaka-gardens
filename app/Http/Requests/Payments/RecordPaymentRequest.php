<?php

namespace App\Http\Requests\Payments;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_ugx' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
