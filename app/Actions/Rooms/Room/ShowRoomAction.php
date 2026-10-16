<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\Services\RoomService;
use App\Http\Resources\Rooms\RoomResource;

class ShowRoomAction
{
    public function __construct(private RoomService $service) {}

    public function execute(string $id): RoomResource
    {
        return new RoomResource($this->service->find($id));
    }
}
