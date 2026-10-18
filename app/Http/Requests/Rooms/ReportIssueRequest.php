<?php

namespace App\Http\Requests\Rooms;

use App\Domains\Rooms\Room\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'description' => ['required', 'string', 'max:255'],
            'close_as' => ['nullable', Rule::in([RoomStatus::UnderMaintenance->value, RoomStatus::OutOfOrder->value])],
        ];
    }
}
