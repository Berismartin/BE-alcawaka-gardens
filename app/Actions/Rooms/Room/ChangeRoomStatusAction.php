<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\DTOs\ChangeRoomStatusDTO;
use App\Domains\Rooms\Room\Services\RoomService;
use App\Http\Resources\Rooms\RoomResource;

class ChangeRoomStatusAction
{
    public function __construct(private RoomService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): RoomResource
    {
        return new RoomResource($this->service->changeStatus($id, ChangeRoomStatusDTO::fromRequest($input)));
    }
}
