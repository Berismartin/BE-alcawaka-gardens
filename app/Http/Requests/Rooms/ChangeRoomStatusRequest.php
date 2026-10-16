<?php

namespace App\Http\Requests\Rooms;

use App\Domains\Rooms\Room\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRoomStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
