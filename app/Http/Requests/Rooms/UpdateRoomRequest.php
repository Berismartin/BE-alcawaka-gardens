<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'required', 'string', 'max:20'],
            'room_type_id' => ['sometimes', 'required', 'uuid', 'exists:room_types,id'],
            'floor' => ['sometimes', 'nullable', 'integer', 'min:-5', 'max:200'],
        ];
    }
}
