<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\Services\RoomService;

class DeleteRoomAction
{
    public function __construct(private RoomService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
