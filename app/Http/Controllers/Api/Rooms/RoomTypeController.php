<?php

namespace App\Http\Controllers\Api\Rooms;

use App\Actions\Rooms\RoomType\CreateRoomTypeAction;
use App\Actions\Rooms\RoomType\DeleteRoomTypeAction;
use App\Actions\Rooms\RoomType\ListRoomTypesAction;
use App\Actions\Rooms\RoomType\ShowRoomTypeAction;
use App\Actions\Rooms\RoomType\UpdateRoomTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\StoreRoomTypeRequest;
use App\Http\Requests\Rooms\UpdateRoomTypeRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoomTypeController extends Controller
{
    /** Public website: active types with photos and rates. */
    public function publicIndex(ListRoomTypesAction $action): JsonResponse
    {
        return $this->respond($action->execute(activeOnly: true));
    }

    /** Staff: every type (a hotel has a handful, so no pagination). */
    public function index(ListRoomTypesAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function show(string $id, ShowRoomTypeAction $action): JsonResponse
    {
        return $this->respond($action->execute($id));
    }

    public function store(StoreRoomTypeRequest $request, CreateRoomTypeAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Room type created.', Response::HTTP_CREATED);
    }

    public function update(UpdateRoomTypeRequest $request, string $id, UpdateRoomTypeAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Room type updated.');
    }

    public function destroy(string $id, DeleteRoomTypeAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Room type deleted.');
    }
}
