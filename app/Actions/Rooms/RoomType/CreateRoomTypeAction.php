<?php

namespace App\Actions\Rooms\RoomType;

use App\Domains\Rooms\RoomType\DTOs\CreateRoomTypeDTO;
use App\Domains\Rooms\RoomType\Services\RoomTypeService;
use App\Http\Resources\Rooms\RoomTypeResource;

class CreateRoomTypeAction
{
    public function __construct(private RoomTypeService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): RoomTypeResource
    {
        return new RoomTypeResource($this->service->create(CreateRoomTypeDTO::fromRequest($input)));
    }
}
