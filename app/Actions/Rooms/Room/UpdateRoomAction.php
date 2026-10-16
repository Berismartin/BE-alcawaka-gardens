<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\DTOs\UpdateRoomDTO;
use App\Domains\Rooms\Room\Services\RoomService;
use App\Http\Resources\Rooms\RoomResource;

class UpdateRoomAction
{
    public function __construct(private RoomService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): RoomResource
    {
        return new RoomResource($this->service->update($id, UpdateRoomDTO::fromRequest($input)));
    }
}
