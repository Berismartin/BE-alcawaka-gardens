<?php

namespace App\Actions\Rooms\RoomType;

use App\Domains\Rooms\RoomType\DTOs\UpdateRoomTypeDTO;
use App\Domains\Rooms\RoomType\Services\RoomTypeService;
use App\Http\Resources\Rooms\RoomTypeResource;

class UpdateRoomTypeAction
{
    public function __construct(private RoomTypeService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): RoomTypeResource
    {
        return new RoomTypeResource($this->service->update($id, UpdateRoomTypeDTO::fromRequest($input)));
    }
}
