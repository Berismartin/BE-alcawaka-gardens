<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\DTOs\CreateRoomDTO;
use App\Domains\Rooms\Room\Services\RoomService;
use App\Http\Resources\Rooms\RoomResource;

class CreateRoomAction
{
    public function __construct(private RoomService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): RoomResource
    {
        return new RoomResource($this->service->create(CreateRoomDTO::fromRequest($input)));
    }
}
