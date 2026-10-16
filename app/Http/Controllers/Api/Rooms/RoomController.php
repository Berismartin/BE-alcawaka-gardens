<?php

namespace App\Http\Controllers\Api\Rooms;

use App\Actions\Rooms\Room\ChangeRoomStatusAction;
use App\Actions\Rooms\Room\CreateRoomAction;
use App\Actions\Rooms\Room\DeleteRoomAction;
use App\Actions\Rooms\Room\PaginateRoomsAction;
use App\Actions\Rooms\Room\ShowRoomAction;
use App\Actions\Rooms\Room\UpdateRoomAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\ChangeRoomStatusRequest;
use App\Http\Requests\Rooms\ListRoomsRequest;
use App\Http\Requests\Rooms\StoreRoomRequest;
use App\Http\Requests\Rooms\UpdateRoomRequest;
use App\Http\Resources\Rooms\RoomResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function index(ListRoomsRequest $request, PaginateRoomsAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), RoomResource::class);
    }

    public function show(string $id, ShowRoomAction $action): JsonResponse
    {
        return $this->respond($action->execute($id));
    }

    public function store(StoreRoomRequest $request, CreateRoomAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Room added.', Response::HTTP_CREATED);
    }

    public function update(UpdateRoomRequest $request, string $id, UpdateRoomAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Room updated.');
    }

    public function destroy(string $id, DeleteRoomAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Room deleted.');
    }

    public function changeStatus(ChangeRoomStatusRequest $request, string $id, ChangeRoomStatusAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Room status updated.');
    }
}
