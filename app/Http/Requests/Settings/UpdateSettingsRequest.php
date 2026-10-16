<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'hotel_name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:160'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'check_in_time' => ['sometimes', 'required', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'required', 'date_format:H:i'],
            'cancellation_policy' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
