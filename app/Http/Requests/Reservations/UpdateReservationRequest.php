<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'check_in' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'check_out' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'adults' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'room_id' => ['sometimes', 'required', 'uuid', 'exists:rooms,id'],
            'special_requests' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
