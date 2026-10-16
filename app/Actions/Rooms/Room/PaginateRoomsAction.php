<?php

namespace App\Actions\Rooms\Room;

use App\Domains\Rooms\Room\Services\RoomService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateRoomsAction
{
    public function __construct(private RoomService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
