<?php

namespace App\Actions\Rooms\RoomType;

use App\Domains\Rooms\RoomType\Services\RoomTypeService;
use App\Http\Resources\Rooms\RoomTypeResource;

class ShowRoomTypeAction
{
    public function __construct(private RoomTypeService $service) {}

    public function execute(string $id): RoomTypeResource
    {
        return new RoomTypeResource($this->service->find($id));
    }
}
