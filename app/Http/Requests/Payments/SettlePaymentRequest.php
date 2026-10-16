<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettlePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['succeeded', 'failed'])]];
    }
}
