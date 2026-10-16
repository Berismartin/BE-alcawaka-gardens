<?php

namespace App\Actions\Rooms\RoomType;

use App\Domains\Rooms\RoomType\Services\RoomTypeService;

class DeleteRoomTypeAction
{
    public function __construct(private RoomTypeService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
