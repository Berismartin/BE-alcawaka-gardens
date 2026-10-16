<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
