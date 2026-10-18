<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class LookupBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:160'],
        ];
    }
}
