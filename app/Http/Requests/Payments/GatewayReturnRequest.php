<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class GatewayReturnRequest extends FormRequest
{
    public function rules(): array
    {
        return ['order_tracking_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/']];
    }
}
