<?php

namespace App\Http\Requests\Rooms;

use App\Domains\Rooms\Room\Enums\RoomStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListRoomsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['number', 'floor', 'status', 'status_changed_at'];
    }

    protected function filters(): array
    {
        return [
            'status' => [Rule::enum(RoomStatus::class)],
            'room_type_id' => ['uuid'],
            'floor' => ['integer'],
        ];
    }
}
