<?php

namespace App\Actions\Rooms\RoomType;

use App\Domains\Rooms\RoomType\Services\RoomTypeService;
use App\Http\Resources\Rooms\RoomTypeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListRoomTypesAction
{
    public function __construct(private RoomTypeService $service) {}

    public function execute(bool $activeOnly = false): AnonymousResourceCollection
    {
        return RoomTypeResource::collection($this->service->list($activeOnly));
    }
}
