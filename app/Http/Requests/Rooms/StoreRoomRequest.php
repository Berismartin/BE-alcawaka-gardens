<?php

namespace App\Http\Requests\Rooms;

use App\Domains\Rooms\Room\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:20'],
            'room_type_id' => ['required', 'uuid', 'exists:room_types,id'],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:200'],
            'status' => ['sometimes', Rule::enum(RoomStatus::class)->except([RoomStatus::Occupied])],
        ];
    }
}
